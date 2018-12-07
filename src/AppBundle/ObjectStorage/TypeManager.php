<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Status;
use AppBundle\Model\Type;

class TypeManager extends PoemManager
{
    protected function setTitles(array &$types): void
    {
        $rawTitles = $this->dbs->getTitles(self::getIds($types));
        foreach ($rawTitles as $rawTitle) {
            $types[$rawTitle['poem_id']]
                ->addTitle($rawTitle['lang'], $rawTitle['title']);
        }
    }

    /**
     * Get types with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $types = [];
        $rawIds = $this->dbs->getIdsByIds($ids);
        if (count($rawIds) == 0) {
            return [];
        }

        foreach ($rawIds as $rawId) {
            $types[$rawId['type_id']] = (new Type())
                ->setId($rawId['type_id']);
        }

        // Remove all ids that did not match above
        $ids = array_keys($types);

        $this->setIncipits($types);

        $this->setNumberOfVerses($types);

        // Verses (needed in mini to calculate number of verses)
        $rawVerses = $this->dbs->getVerses($ids);
        foreach ($rawVerses as $rawVerse) {
            $types[$rawVerse['type_id']]
                ->setVerses(array_map('trim', explode("\n", $rawVerse['text_content'])));
        }

        $this->setPublics($types);

        return $types;
    }

    /**
     * Get types with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $types = $this->getMini($ids);

        // Remove all ids that did not match above
        $ids = array_keys($types);

        $this->setTitles($types);

        $this->setMeters($types);

        $this->setSubjects($types);

        $rawKeywords = $this->dbs->getKeywords($ids);
        $keywordIds = self::getUniqueIds($rawKeywords, 'keyword_id');
        $keywords = $this->container->get('keyword_manager')->get($keywordIds);
        foreach ($rawKeywords as $rawKeyword) {
            $types[$rawKeyword['type_id']]
                ->addKeyword($keywords[$rawKeyword['keyword_id']]);
        }

        $this->setPersonRoles($types);

        $this->setGenres($types);

        $this->setComments($types);

        // statuses
        $rawStatuses = $this->dbs->getStatuses($ids);
        $statuses = $this->container->get('status_manager')->getWithData($rawStatuses);
        foreach ($rawStatuses as $rawStatus) {
            switch ($rawStatus['status_type']) {
                case Status::TYPE_TEXT:
                    $types[$rawStatus['type_id']]
                        ->setTextStatus($statuses[$rawStatus['status_id']]);
                    break;
                case Status::TYPE_CRITICAL:
                    $types[$rawStatus['type_id']]
                        ->setCriticalStatus($statuses[$rawStatus['status_id']]);
                    break;
            }
        }

        $this->setAcknowledgements($types);

        // occurrences (needed in short to calculate number of occurrences)
        $rawOccurrences = $this->dbs->getOccurrences($ids);
        if (!empty($rawOccurrences)) {
            $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
            $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
            foreach ($rawOccurrences as $rawOccurrence) {
                $types[$rawOccurrence['type_id']]->addOccurrence($occurrences[$rawOccurrence['occurrence_id']]);
            }
        }

        // Needed to index DBBE in elasticsearch
        $this->setBibliographies($types);

        $this->setIdentifications($types);

        $this->setManagements($types);

        return $types;
    }

    /**
     * Get a single type with all information
     * @param  int  $id
     * @return Type
     */
    public function getFull(int $id): Type
    {
        // Get basic occurrence information
        $types = $this->getShort([$id]);
        if (count($types) == 0) {
            throw new NotFoundHttpException('Type with id ' . $id .' not found.');
        }

        $this->setModifieds($types);

        $this->setPrevIds($types);

        $type = $types[$id];

        // related types
        $rawRelTypes = $this->dbs->getRelatedTypes([$id]);
        if (!empty($rawRelTypes)) {
            $typeIds = self::getUniqueIds($rawRelTypes, 'rel_type_id');
            $relTypes =  $this->getMini($typeIds);
            $typeRelTypes = $this->container->get('type_relation_type_manager')->getWithData($rawRelTypes);
            foreach ($rawRelTypes as $rawRelType) {
                $type->addRelatedType(
                    $relTypes[$rawRelType['rel_type_id']],
                    $typeRelTypes[$rawRelType['type_relation_type_id']]
                );
            }
        }

        // critical apparatus
        $rawCriticalApparatuses = $this->dbs->getCriticalApparatuses([$id]);
        if (!empty($rawCriticalApparatuses)) {
            $type->setCriticalApparatus($rawCriticalApparatuses[0]['critical_apparatus']);
        }

        // translation
        $rawTranslations = $this->dbs->getTranslations([$id]);
        $translationIds = self::getUniqueIds($rawTranslations, 'translation_id');
        if (!empty($translationIds)) {
            $translations = $this->container->get('translation_manager')->get($translationIds);
            $type->setTranslations($translations);
        }

        // based on occurrence
        $rawBasedOns = $this->dbs->getBasedOns([$id]);
        $occurrenceIds = self::getUniqueIds($rawBasedOns, 'occurrence_id');
        $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
        if (!empty($rawBasedOns)) {
            $type->setBasedOn($occurrences[$rawBasedOns[0]['occurrence_id']]);
        }

        return $type;
    }

    public function getNewId(int $oldId): int
    {
        $rawId = $this->dbs->getNewId($oldId);
        if (count($rawId) != 1) {
            throw new NotFoundHttpException('The type with legacy id "' . $oldId . '" does not exist.');
        }
        return $rawId[0]['new_id'];
    }

    public function getOccurrenceDependencies(int $occurrenceId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOccurrenceId($occurrenceId), $method);
    }

    public function getTranslationDependencies(int $translationId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByTranslationId($translationId), $method);
    }

    /**
     * Add a new type
     * @param  stdClass $data
     * @return Type
     */
    public function add(stdClass $data): Type
    {
        // Incipit is a required fields
        if (!property_exists($data, 'incipit')
            || !is_string($data->incipit)
            || empty($data->incipit)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new type.');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->incipit);

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update a new or existing type
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new person
     * @return Type
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Type
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Person with id ' . $id .' not found.');
            }

            // update person data
            $changes = [
                'mini' => $isNew,
                'short' => $isNew,
                'full' => $isNew,
            ];
            if (property_exists($data, 'public')) {
                if (!is_bool($data->public)) {
                    throw new BadRequestHttpException('Incorrect public data.');
                }
                $changes['mini'] = true;
                $this->updatePublic($old, $data->public);
            }
            if (property_exists($data, 'incipit')) {
                // Incipit is a required field
                if (!is_string($data->incipit)
                    || empty($data->incipit)
                ) {
                    throw new BadRequestHttpException('Incorrect incipit data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateIncipit($id, $data->incipit);
            }
            if (property_exists($data, 'title_GR')) {
                if (!is_string($data->title_GR)) {
                    throw new BadRequestHttpException('Incorrect Greek title data.');
                }

                $changes['short'] = true;
                $this->dbs->upsertTitle($id, 'GR', $data->title_GR);
            }
            if (property_exists($data, 'title_LA')) {
                if (!is_string($data->title_LA)) {
                    throw new BadRequestHttpException('Incorrect Latin title data.');
                }

                $changes['short'] = true;
                $this->dbs->upsertTitle($id, 'LA', $data->title_LA);
            }
            if (property_exists($data, 'numberOfVerses')) {
                if (!is_numeric($data->numberOfVerses)) {
                    throw new BadRequestHttpException('Incorrect number of verses data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateNumberOfVerses($id, $data->numberOfVerses);
            }
            if (property_exists($data, 'verses')) {
                if (!is_string($data->verses)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateVerses($id, $data->verses);
            }
            if (property_exists($data, 'relatedTypes')) {
                if (!is_array($data->relatedTypes)) {
                    throw new BadRequestHttpException('Incorrect related types data.');
                }
                $changes['full'] = true;
                $this->updateTypes($old, $data->relatedTypes);
            }
            $roles = $this->container->get('role_manager')->getByType('type');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['short'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'meters')) {
                if (!is_array($data->meters)) {
                    throw new BadRequestHttpException('Incorrect meter data.');
                }
                $changes['short'] = true;
                $this->updateMeters($old, $data->meters);
            }
            if (property_exists($data, 'genres')) {
                if (!is_array($data->genres)) {
                    throw new BadRequestHttpException('Incorrect genre data.');
                }
                $changes['short'] = true;
                $this->updateGenres($old, $data->genres);
            }
            if (property_exists($data, 'personSubjects')) {
                if (!is_array($data->personSubjects)) {
                    throw new BadRequestHttpException('Incorrect person subject data.');
                }
                $changes['short'] = true;
                $this->updatePersonSubjects($old, $data->personSubjects);
            }
            if (property_exists($data, 'keywordSubjects')) {
                if (!is_array($data->keywordSubjects)) {
                    throw new BadRequestHttpException('Incorrect keyword subject data.');
                }
                $changes['short'] = true;
                $this->updateKeywordSubjects($old, $data->keywordSubjects);
            }
            if (property_exists($data, 'keywords')) {
                if (!is_array($data->keywords)) {
                    throw new BadRequestHttpException('Incorrect keywords data.');
                }
                $changes['short'] = true;
                $this->updateKeywords($old, $data->keywords);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'type');
            if (property_exists($data, 'bibliography')) {
                if (!is_object($data->bibliography)) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                // short is needed here to index DBBE in elasticsearch
                $changes['short'] = true;
                $this->updateBibliography($old, $data->bibliography, true);
            }
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePublicComment($id, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }
            if (property_exists($data, 'criticalApparatus')) {
                if (!is_string($data->criticalApparatus)) {
                    throw new BadRequestHttpException('Incorrect critical apparatus data.');
                }
                $changes['full'] = true;
                $this->dbs->updateCriticalApparatus($id, $data->criticalApparatus);
            }
            if (property_exists($data, 'translations')) {
                if (!is_array($data->translations)) {
                    throw new BadRequestHttpException('Incorrect translations data.');
                }
                $changes['full'] = true;
                $this->updateTranslations($old, $data->translations);
            }
            if (property_exists($data, 'acknowledgements')) {
                if (!is_array($data->acknowledgements)) {
                    throw new BadRequestHttpException('Incorrect acknowledgements data.');
                }
                $changes['short'] = true;
                $this->updateAcknowledgements($old, $data->acknowledgements);
            }
            if (property_exists($data, 'criticalStatus')) {
                if (!(is_object($data->criticalStatus) || empty($data->criticalStatus))) {
                    throw new BadRequestHttpException('Incorrect record status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->criticalStatus, Status::TYPE_CRITICAL);
            }
            if (property_exists($data, 'textStatus')) {
                if (!(is_object($data->textStatus) || empty($data->textStatus))) {
                    throw new BadRequestHttpException('Incorrect text status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->textStatus, Status::TYPE_TEXT);
            }
            if (property_exists($data, 'basedOn')) {
                if (!is_object($data->basedOn)) {
                    throw new BadRequestHttpException('Incorrect based on data.');
                }
                $changes['full'] = true;
                $this->updateBasedOn($old, $data->basedOn);
            }
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // Reset elasticsearch
            $this->ess->add($new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // reset elasticsearch
            if (!$isNew && isset($new)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }

    public function updateTypes(Type $type, array $relatedTypes)
    {
        $typeIds = [];
        foreach ($relatedTypes as $relatedType) {
            if (!is_object($relatedType)
                || !property_exists($relatedType, 'type')
                || !is_object($relatedType->type)
                || !property_exists($relatedType->type, 'id')
                || !is_numeric($relatedType->type->id)
                || !property_exists($relatedType, 'relationTypes')
                || !is_array($relatedType->relationTypes)
                || in_array($relatedType->type->id, $typeIds)
            ) {
                throw new BadRequestHttpException('Incorrect related type data.');
            }
            foreach ($relatedType->relationTypes as $relationType) {
                if (!property_exists($relationType, 'id')
                    || !is_numeric($relationType->id)
                ) {
                    throw new BadRequestHttpException('Incorrect relation type data.');
                }
            }
            $typeIds[] = $relatedType->type->id;
        }

        // Only use type information to calculate diff
        $newTypes = array_map(
            function ($relatedType) {
                return $relatedType->type;
            },
            $relatedTypes
        );
        $oldTypes = array_map(
            function ($relatedType) {
                return $relatedType[0];
            },
            $type->getRelatedTypes()
        );
        list($delIds, $addIds) = self::calcDiff($newTypes, $oldTypes);

        if (count($delIds) > 0) {
            $this->dbs->delRelatedTypes($type->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            foreach ($relatedTypes as $relatedType) {
                if ($relatedType->type->id != $addId) {
                    continue;
                } else {
                    $relationTypeIds = array_map(
                        function ($relationType) {
                            return $relationType->id;
                        },
                        $relatedType->relationTypes
                    );
                    $this->dbs->addRelatedType($type->getId(), $addId, $relationTypeIds);
                    break;
                }
            }
        }

        foreach ($relatedTypes as $relatedType) {
            if (!in_array($relatedType->type->id, $addIds)) {
                foreach ($type->getRelatedTypes() as $oldRelatedType) {
                    if ($oldRelatedType[0]->getId() != $relatedType->type->id) {
                        continue;
                    } else {
                        list($relDelIds, $relAddIds) = self::calcDiff($relatedType->relationTypes, $oldRelatedType[1]);

                        if (count($relDelIds) > 0) {
                            $this->dbs->delRelatedTypeRelations($type->getId(), $relatedType->type->id, $relDelIds);
                        }
                        if (count($relAddIds) > 0) {
                            $this->dbs->addRelatedType($type->getId(), $relatedType->type->id, $relAddIds);
                        }
                        break;
                    }
                }
            }
        }
    }

    private function updateKeywords(Type $type, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            if (!is_object($keyword)
                || !property_exists($keyword, 'id')
                || !is_numeric($keyword->id)
            ) {
                throw new BadRequestHttpException('Incorrect keyword data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($keywords, $type->getKeywords());

        if (count($delIds) > 0) {
            $this->dbs->delKeywords($type->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addKeyword($type->getId(), $addId);
        }
    }

    private function updateTranslations(Type $type, array $translations): void
    {
        $delIds = [];
        foreach ($type->getTranslations() as $oldTranslation) {
            $found = false;
            foreach ($translations as $newTranslation) {
                if (property_exists($newTranslation, 'id') && $oldTranslation->getId() == $newTranslation->id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $delIds[] = $oldTranslation->getId();
            }
        }

        foreach ($delIds as $delId) {
            $this->container->get('translation_manager')->delete($delId);
        }

        foreach ($translations as $newTranslation) {
            // If a new translation has an id, it will be present in old translations
            if (property_exists($newTranslation, 'id')) {
                $this->container->get('translation_manager')->updateIfRequired(
                    $type->getTranslations()[$newTranslation->id],
                    $newTranslation
                );
            } else {
                $this->container->get('translation_manager')->add(
                    $newTranslation
                );
            }
        }
    }

    private function updateBasedOn(Type $type, stdClass $basedOn = null): void
    {
        if (empty($basedOn)) {
            $this->dbs->delBasedOn($type->getId());
        } elseif (!is_object($basedOn)
            || !property_exists($basedOn, 'id')
            || !is_numeric($basedOn->id)
        ) {
            throw new BadRequestHttpException('Incorrect based on data.');
        } else {
            if (empty($type->getBasedOn())) {
                $this->dbs->addBasedOn($type->getId(), $basedOn->id);
            } else {
                $this->dbs->updateBasedOn($type->getId(), $basedOn->id);
            }
        }
    }
}
