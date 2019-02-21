<?php

namespace AppBundle\ObjectStorage;

use DateTime;
use Exception;
use stdClass;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Entity;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Identification;
use AppBundle\Model\Identifier;

class EntityManager extends ObjectManager
{
    /**
     * Get entities with enough information for the sitemap: id and modification date
     * @param  array $ids
     * @return array
     */
    public function getSitemap(array $ids): array
    {
        $entities = [];
        foreach ($ids as $id) {
            $entities[$id] = (new Entity())
                ->setId($id);
        }

        $this->setModifieds($entities);

        return $entities;
    }

    /**
     * Get the last modification timestamp of an entity of a specific type
     * @return DateTime
     */
    public function getLastModified(): DateTime
    {
        return new DateTime($this->dbs->getLastModified()['modified']);
    }

    public function getAllShortJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedShortJson('all', $sortFunction);
    }

    public function getAllJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedJson('all', $sortFunction);
    }

    public function getAllSitemap(string $sortFunction = null): array
    {
        return $this->getAllCombined('sitemap', $sortFunction);
    }

    public function getAllMicroShortJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedShortJson('micro', $sortFunction);
    }

    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedShortJson('mini', $sortFunction);
    }

    public function getAllShort(string $sortFunction = null): array
    {
        return $this->getAllCombined('short', $sortFunction);
    }

    private function getAllCombined(string $level, string $sortFunction = null): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, $this->entityType . '_id');

        switch ($level) {
            case 'all':
                $objects = $this->get($ids);
                break;
            case 'micro':
                $objects = $this->getMicro($ids);
                break;
            case 'mini':
                $objects = $this->getMini($ids);
                break;
            case 'short':
                $objects = $this->getShort($ids);
                break;
            case 'sitemap':
                $objects = $this->getSitemap($ids);
                break;
        }

        if (!empty($sortFunction)) {
            usort($objects, function ($a, $b) use ($sortFunction) {
                if ($sortFunction == 'getId') {
                    return $a->{$sortFunction}() > $b->{$sortFunction}();
                }
                return strcmp($a->{$sortFunction}(), $b->{$sortFunction}());
            });
        }

        return $objects;
    }

    private function getAllCombinedShortJson(string $level, string $sortFunction = null): array
    {
        return $this->wrapArrayCache(
            $this->entityType . 's_' . $level . (!empty($sortFunction) ? '_' . $sortFunction : ''),
            [$this->entityType . 's'],
            function () use ($level, $sortFunction) {
                return ArrayToJson::arrayToShortJson($this->getAllCombined($level, $sortFunction));
            }
        );
    }

    private function getAllCombinedJson(string $level, string $sortFunction = null): array
    {
        return ArrayToJson::arrayToJson($this->getAllCombined($level, $sortFunction));
    }

    public function getArticleDependencies(int $articleId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByArticleId($articleId), $method);
    }

    public function getBookDependencies(int $bookId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookId($bookId), $method);
    }

    public function getBookChapterDependencies(int $bookChapterId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookChapterId($bookChapterId), $method);
    }

    public function getOnlineSourceDependencies(int $onlineSourceId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOnlineSourceId($onlineSourceId), $method);
    }

    public function getManagementDependencies(int $managementId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByManagementId($managementId), $method);
    }

    protected function setPublics(array &$entities): void
    {
        $rawPublics = $this->dbs->getPublics(array_keys($entities));
        foreach ($rawPublics as $rawPublic) {
            $entities[$rawPublic['entity_id']]
                // default: true (if no value is set in the database)
                ->setPublic(isset($rawPublic['public']) ? $rawPublic['public'] : true);
        }
    }

    protected function setModifieds(array &$entities): void
    {
        $rawModifieds = $this->dbs->getModifieds(array_keys($entities));
        foreach ($rawModifieds as $rawModified) {
            $entities[$rawModified['entity_id']]
                // default: true (if no value is set in the database)
                ->setModified(new DateTime($rawModified['modified']));
        }
    }

    protected function setComments(array &$entities): void
    {
        $rawComments = $this->dbs->getComments(array_keys($entities));
        foreach ($rawComments as $rawComment) {
            $entities[$rawComment['entity_id']]
                ->setPublicComment($rawComment['public_comment'])
                ->setPrivateComment($rawComment['private_comment']);
        }
    }

    protected function setIdentifications(array &$entities): void
    {
        $rawIdentifications = $this->dbs->getIdentifications(array_keys($entities));
        $identifiers = $this->container->get('identifier_manager')->getWithData($rawIdentifications);
        foreach ($rawIdentifications as $rawIdentification) {
            $entities[$rawIdentification['entity_id']]->addIdentifications(
                Identification::constructFromDB(
                    $identifiers[$rawIdentification['identifier_id']],
                    json_decode($rawIdentification['identifications']),
                    json_decode($rawIdentification['identification_volumes']),
                    json_decode($rawIdentification['identification_extras'])
                )
            );
        }
    }

    protected function setBibliographies(array &$entities): void
    {
        $rawBibliographies = $this->dbs->getBibliographies(array_keys($entities));
        if (!empty($rawBibliographies)) {
            $ids = self::getUniqueIds($rawBibliographies, 'reference_id');
            $bibliographies = $this->container->get('bibliography_manager')->get($ids);

            foreach ($rawBibliographies as $rawBibliography) {
                $entities[$rawBibliography['entity_id']]
                    ->addBibliography($bibliographies[$rawBibliography['reference_id']]);
            }
        }
    }

    protected function setInverseBibliographies(array &$entities): void
    {
        $rawInverseBibliographies = $this->dbs->getInverseBibliographies(array_keys($entities));
        if (!empty($rawInverseBibliographies)) {
            $inverseBibliographies = [];
            foreach (['manuscript', 'occurrence', 'type', 'person'] as $type) {
                $ids = self::getUniqueIds($rawInverseBibliographies, 'entity_id', 'type', $type);
                $inverseBibliographies += $this->container->get($type . '_manager')->getMini($ids);
            }
            // Add linked type instead of translation
            $translationIds = self::getUniqueIds($rawInverseBibliographies, 'entity_id', 'type', 'translation');
            foreach ($translationIds as $translationId) {
                $types = $this->container->get('type_manager')->getTranslationDependencies($translationId, 'getMini');
                $inverseBibliographies[$translationId] = reset($types);
            }

            foreach ($rawInverseBibliographies as $rawInverseBibliography) {
                $entities[$rawInverseBibliography['biblio_id']]
                    ->addInverseBibliography(
                        $inverseBibliographies[$rawInverseBibliography['entity_id']],
                        $rawInverseBibliography['type']
                    );
            }

            $biblioIds = self::getUniqueIds($rawInverseBibliographies, 'biblio_id');
            foreach ($biblioIds as $biblioId) {
                $entities[$biblioId]->sortInverseBibliographies();
            }
        }
    }

    protected function setManagements(array &$entities): void
    {
        $rawManagements = $this->dbs->getManagements(array_keys($entities));
        if (!empty($rawManagements)) {
            $managements = $this->container->get('management_manager')->getWithData($rawManagements);

            foreach ($rawManagements as $rawManagement) {
                $entities[$rawManagement['entity_id']]
                    ->addManagement($managements[$rawManagement['management_id']]);
            }
        }
    }

    protected function updatePublic(Entity $entity, bool $public): void
    {
        $this->dbs->updatePublic($entity->getId(), $public);
    }

    protected function updateDate(Entity $entity, string $type, bool $exists = false, stdClass $newdate = null): void
    {
        if (!property_exists($newdate, 'floor') || (!empty($newdate->floor) && !is_string($newdate->floor))
            || !property_exists($newdate, 'ceiling') || (!empty($newdate->ceiling) && !is_string($newdate->ceiling))
        ) {
            throw new BadRequestHttpException('Incorrect date data.');
        } elseif (empty($newdate->floor) && empty($newdate->ceiling)) {
            $this->dbs->deleteDateOrInterval($entity->getId(), $type);
        } else {
            $dbDate = '('
                . (empty($newdate->floor) ? '-infinity' : $newdate->floor)
                . ', '
                . (empty($newdate->ceiling) ? 'infinity' : $newdate->ceiling)
                . ')';
            if (!$exists) {
                $this->dbs->insertDate($entity->getId(), $type, $dbDate);
            } else {
                $this->dbs->updateDate($entity->getId(), $type, $dbDate);
            }
        }
    }

    protected function updateInterval(Entity $entity, string $type, bool $exists = False, stdClass $startDate = null, stdClass $endDate = null): void
    {
        if (!property_exists($startDate, 'floor') || (!empty($startDate->floor) && !is_string($startDate->floor))
            || !property_exists($startDate, 'ceiling') || (!empty($startDate->ceiling) && !is_string($startDate->ceiling))
            || !property_exists($endDate, 'floor') || (!empty($endDate->floor) && !is_string($endDate->floor))
            || !property_exists($endDate, 'ceiling') || (!empty($endDate->ceiling) && !is_string($endDate->ceiling))
        ) {
            throw new BadRequestHttpException('Incorrect interval data.');
        } elseif (empty($startDate->floor) && empty($startDate->ceiling)) {
            $this->dbs->deleteDateOrInterval($entity->getId(), $type);
        } elseif (empty($endDate->floor) && empty($endDate->ceiling)) {
            $this->updateDate($entity, $type, $exists, $startDate);
        } else {
            $dbInterval = '('
                . (empty($startDate->floor) ? '-infinity' : $startDate->floor)
                . ', '
                . (empty($startDate->ceiling) ? 'infinity' : $startDate->ceiling)
                . ', '
                . (empty($endDate->floor) ? '-infinity' : $endDate->floor)
                . ', '
                . (empty($endDate->ceiling) ? 'infinity' : $endDate->ceiling)
                . ')';
            if (!$exists) {
                $this->dbs->insertInterval($entity->getId(), $type, $dbInterval);
            } else {
                $this->dbs->updateInterval($entity->getId(), $type, $dbInterval);
            }
        }
    }

    protected function updateIdentificationwrapper(
        Entity $entity,
        stdClass $data,
        array &$changes,
        string $level,
        string $entityType
    ): void {
        if (property_exists($data, 'identification')) {
            if (!is_object($data->identification)) {
                throw new BadRequestHttpException('Incorrect identification data.');
            }
            $identifiers = $this->container->get('identifier_manager')->getByType($entityType);
            foreach ($identifiers as $identifier) {
                if (property_exists($data->identification, $identifier->getSystemName())) {
                    if (
                        !empty($data->identification->{$identifier->getSystemName()})
                        && !is_array($data->identification->{$identifier->getSystemName()})
                    )  {
                        throw new BadRequestHttpException('Incorrect identification data.');
                    }
                    if (!empty($data->identification->{$identifier->getSystemName()})) {
                        foreach ($data->identification->{$identifier->getSystemName()} as $identification) {
                            if (
                                !property_exists($identification, 'identification')
                                || empty($identification->identification)
                                || !is_string($identification->identification)
                                || !preg_match('~' . $identifier->getRegex() . '~', $identification->identification)
                            ) {
                                throw new BadRequestHttpException('Incorrect identification identification data.');
                            }
                            if ($identifier->getVolumes() > 1) {
                                if (
                                    !property_exists($identification, 'volume')
                                    || !is_numeric($identification->volume)
                                ) {
                                    throw new BadRequestHttpException('Incorrect identification volume data.');
                                }
                            }
                            if ($identifier->getExtra()) {
                                if (
                                    !property_exists($identification, 'extra')
                                    || empty($identification->extra)
                                    || !is_string($identification->extra)
                                ) {
                                    throw new BadRequestHttpException('Incorrect identification extra data.');
                                }
                            }
                        }
                    }
                }
            }
            $changes[$level] = true;
            foreach ($identifiers as $identifier) {
                if (property_exists($data->identification, $identifier->getSystemName())) {
                    $oldIdentifications = !isset($entity->getIdentifications()[$identifier->getSystemName()]) ? [] : $entity->getIdentifications()[$identifier->getSystemName()][1];
                    $newIdentifications = $data->identification->{$identifier->getSystemName()};

                    if ($identifier->getVolumes() == 1) {
                        $this->updateIdentification(
                            $entity,
                            $identifier,
                            $oldIdentifications,
                            $newIdentifications
                        );
                    } else {
                        $oldVolumes = array_unique(array_map(function ($oldIdentification) { return $oldIdentification->getVolume(); }, $oldIdentifications));
                        $newVolumes = array_unique(array_map(function ($newIdentification) { return $newIdentification->volume; }, $newIdentifications));
                        $allVolumes = array_merge($oldVolumes, $newVolumes);

                        foreach ($allVolumes as $volume) {
                            $this->updateIdentification(
                                $entity,
                                $identifier,
                                array_filter($oldIdentifications, function ($oldIdentification) use ($volume) { return $oldIdentification->getVolume() == $volume; }),
                                array_filter($newIdentifications, function ($newIdentification) use ($volume) { return $newIdentification->volume == $volume; }),
                                $volume
                            );
                        }
                    }
                }
            }
        }
    }

    protected function updateIdentification(Entity $entity, Identifier $identifier, array $old, array $new = null, int $volume = null): void
    {
        // Old value, but no new value => delete
        if (!empty($old) && empty($new)) {
            $this->dbs->delIdentification($entity->getId(), $identifier->getId(), $volume);
        // Insert or update
        } else {
            $identificationValue = implode('|', array_map(function($identification) {return $identification->identification;}, $new));
            $extraValue = null;
            if ($identifier->getExtra()) {
                $extraValue = implode('|', array_map(function($identification) {return $identification->extra;}, $new));
            }
            // Insert
            if (empty($old)) {
                $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), $identificationValue, $extraValue, $volume);
            }
            // Update if necessary
            else {
                $oldExtraValue = null;
                $oldIdentificationValue = implode('|', array_map(function($oldIdentification) {return $oldIdentification->getIdentification();}, $old));
                if ($identifier->getExtra()) {
                    $oldExtraValue = implode('|', array_map(function($oldIdentification) {return $oldIdentification->getExtra();}, $old));
                }
                if ($oldIdentificationValue !== $identificationValue || $oldExtraValue !== $extraValue) {
                    $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), $identificationValue, $extraValue, $volume);
                }
            }
        }
    }

    protected function updateIdentificationExtra(Entity $entity, Identifier $identifier, string $extra): void
    {
        $this->dbs->beginTransaction();
        try {
            $this->dbs->updateIdentificationExtra($entity->getId(), $identifier->getId(), $extra);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
    }

    protected function updateBibliography(
        Entity $entity,
        stdClass $bibliography,
        bool $referenceTypeRequired = false
    ): void {
        // Verify input
        foreach (['book', 'article', 'bookChapter', 'onlineSource'] as $bibType) {
            $plurBibType = $bibType . 's';
            if (!property_exists($bibliography, $plurBibType) || !is_array($bibliography->$plurBibType)) {
                throw new BadRequestHttpException('Incorrect bibliography data.');
            }
            foreach ($bibliography->$plurBibType as $bib) {
                if (!is_object($bib)
                    || (property_exists($bib, 'id') && (empty($bib->id) || !is_numeric($bib->id)))
                    || !property_exists($bib, $bibType) || !is_object($bib->$bibType)
                    || !property_exists($bib->$bibType, 'id') || !is_numeric($bib->$bibType->id)
                    || ($referenceTypeRequired
                        && (
                            !property_exists($bib, 'referenceType')
                            || !is_object($bib->referenceType)
                            || !property_exists($bib->referenceType, 'id')
                            || !is_numeric($bib->referenceType->id)
                        )
                    )
                    || (property_exists($bib, 'image') && !is_string($bib->image))
                ) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                if (in_array($bibType, ['book', 'article', 'bookChapter'])) {
                    if (!property_exists($bib, 'startPage') || !(empty($bib->startPage) || is_string($bib->startPage))
                        || !property_exists($bib, 'endPage')  || !(empty($bib->endPage) || is_string($bib->endPage))
                        || (property_exists($bib, 'rawPages') && !(empty($bib->rawPages) || is_string($bib->rawPages)))
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                } else {
                    if (!property_exists($bib, 'relUrl') || !(empty($bib->relUrl) ||is_string($bib->relUrl))
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                }
            }
        }

        // Add and update
        $oldBibIds = array_keys($entity->getBibliographies());
        $newBibIds = [];
        $this->dbs->beginTransaction();
        try {
            foreach (['article', 'book', 'bookChapter', 'onlineSource'] as $bibType) {
                $plurBibType = $bibType . 's';
                foreach ($bibliography->$plurBibType as $bib) {
                    if (!property_exists($bib, 'id')) {
                        // Add new
                        if (in_array($bibType, ['book', 'article', 'bookChapter'])) {
                            $newBib = $this->container->get('bibliography_manager')->add(
                                $entity->getId(),
                                $bib->{$bibType}->id,
                                self::certainString($bib, 'startPage'),
                                self::certainString($bib, 'endPage'),
                                null,
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $newBibIds[] = $newBib->getId();
                        } else {
                            // onlineSource
                            $newBib = $this->container->get('bibliography_manager')->add(
                                $entity->getId(),
                                $bib->{$bibType}->id,
                                null,
                                null,
                                self::certainString($bib, 'relUrl'),
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $newBibIds[] = $newBib->getId();
                        }
                    } elseif (in_array($bib->id, $oldBibIds)) {
                        $newBibIds[] = $bib->id;
                        // Update
                        if (in_array($bibType, ['book', 'article', 'bookChapter'])) {
                            $this->container->get('bibliography_manager')->update(
                                $bib->id,
                                $bib->{$bibType}->id,
                                self::certainString($bib, 'startPage'),
                                self::certainString($bib, 'endPage'),
                                self::certainString($bib, 'rawPages'),
                                null,
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                        } else {
                            // onlineSource
                            $this->container->get('bibliography_manager')->update(
                                $bib->id,
                                $bib->{$bibType}->id,
                                null,
                                null,
                                null,
                                self::certainString($bib, 'relUrl'),
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                        }
                    } else {
                        throw new NotFoundHttpException(
                            'Bibliography with id "' . $bib->id . '" not found '
                            . ' in entity with id "' . $entity->getId() . '".'
                        );
                    }
                }
            }

            // delete
            $delIds = array_diff($oldBibIds, $newBibIds);
            if (count($delIds) > 0) {
                $this->container->get('bibliography_manager')->deleteMultiple($delIds);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            throw $e;
        }
    }

    protected function updateManagementWrapper(
        Entity $entity,
        stdClass $data,
        array &$changes,
        string $level
    ): void {
        if (property_exists($data, 'managements')) {
            if (!is_array($data->managements)) {
                throw new BadRequestHttpException('Incorrect managements data.');
            }
            foreach ($data->managements as $management) {
                if (!is_object($management)
                    || !property_exists($management, 'id')
                    || !is_numeric($management->id)
                ) {
                    throw new BadRequestHttpException('Incorrect management data.');
                }
            }
            $changes[$level] = true;

            list($delIds, $addIds) = self::calcDiff($data->managements, $entity->getManagements());

            if (count($delIds) > 0) {
                $this->dbs->delManagements($entity->getId(), $delIds);
            }
            foreach ($addIds as $addId) {
                $this->dbs->addManagement($entity->getId(), $addId);
            }
        }
    }

    public function updateElasticManagement(array $ids): void
    {
        if (!empty($ids)) {
            $rawManagements = $this->dbs->getManagements($ids);
            if (!empty($rawManagements)) {
                $managements = $this->container->get('management_manager')->getWithData($rawManagements);
                $data = [];

                foreach ($rawManagements as $rawManagement) {
                    if (!isset($data[$rawManagement['entity_id']])) {
                        $data[$rawManagement['entity_id']] = [
                            'id' => $rawManagement['entity_id'],
                            'management' => [],
                        ];
                    }
                    $data[$rawManagement['entity_id']]['management'][] =
                        $managements[$rawManagement['management_id']]->getShortJson();
                }

                $this->ess->updateMultiple($data);
            }
        }
    }

    public function addManagements(stdClass $data): void
    {
        if (!property_exists($data, 'ids') && !property_exists($data, 'filter')
        ) {
            throw new BadRequestHttpException('Incorrect data.');
        }
        if (property_exists($data, 'filter')) {
            if (!is_object($data->filter)) {
                throw new BadRequestHttpException('Incorrect filter data.');
            }
            $data->ids = $this->ess->getAllResults($data->filter);
        }
        if (property_exists($data, 'ids')) {
            if (!is_array($data->ids)) {
                throw new BadRequestHttpException('Incorrect ids data.');
            }
            foreach ($data->ids as $id) {
                if (!is_numeric($id)) {
                    throw new BadRequestHttpException('Incorrect id data.');
                }
            }
        }
        if (!property_exists($data, 'managements')
            || !is_array($data->managements)
        ) {
            throw new BadRequestHttpException('Incorrect managements data.');
        }
        foreach ($data->managements as $management) {
            if (!is_object($management)
                || !property_exists($management, 'id')
                || !is_numeric($management->id)
            ) {
                throw new BadRequestHttpException('Incorrect management data.');
            }
        }

        $entities = $this->getShort($data->ids);

        $addIds = [];
        foreach ($entities as $entity) {
            foreach ($data->managements as $newMan) {
                $exists = false;

                foreach ($entity->getManagements() as $oldMan) {
                    if ($newMan->id == $oldMan->getId()) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    if (!isset($addIds[$entity->getId()])) {
                        $addIds[$entity->getId()] = [];
                    }
                    $addIds[$entity->getId()][] = $newMan->id;
                }
            }
        }

        // Update entities
        if (!empty($addIds)) {
            $this->dbs->beginTransaction();
            try {
                // update management collections
                foreach ($addIds as $entityId => $ids) {
                    foreach ($ids as $id) {
                        $this->dbs->addManagement($entityId, $id);
                    }
                }

                // update log with minimal data (id, managements)
                $rawManagements = $this->dbs->getManagements(array_keys($addIds));
                $newEntities = [];
                foreach (array_keys($addIds) as $id) {
                    $newEntities[$id] = (new Entity())
                        ->setId($id);
                }
                if (!empty($rawManagements)) {
                    $managements = $this->container->get('management_manager')->getWithData($rawManagements);

                    foreach ($rawManagements as $rawManagement) {
                        $newEntities[$rawManagement['entity_id']]
                            ->addManagement($managements[$rawManagement['management_id']]);
                    }
                }

                foreach ($newEntities as $newEntity) {
                    $old = (new Entity())
                        ->setId($newEntity->getId())
                        ->setManagements($entities[$newEntity->getId()]->getManagements());
                    $this->updateModified($old, $newEntity);
                }

                // update data to update elastic search
                $esData = [];
                foreach ($newEntities as $newEntity) {
                    $esData[$newEntity->getId()] = [
                        'id' => $newEntity->getId(),
                        'management' => ArrayToJson::arrayToShortJson($newEntity->getManagements()),
                    ];
                }
                $this->ess->updateMultiple($esData);

                // commit transaction
                $this->dbs->commit();
            } catch (\Exception $e) {
                $this->dbs->rollBack();

                throw $e;
            }
        }
    }

    public function removeManagements(stdClass $data): void
    {
        if (!property_exists($data, 'ids') && !property_exists($data, 'filter')
        ) {
            throw new BadRequestHttpException('Incorrect data.');
        }
        if (property_exists($data, 'filter')) {
            if (!is_object($data->filter)) {
                throw new BadRequestHttpException('Incorrect filter data.');
            }
            $data->ids = $this->ess->getAllResults($data->filter);
        }
        if (property_exists($data, 'ids')) {
            if (!is_array($data->ids)) {
                throw new BadRequestHttpException('Incorrect ids data.');
            }
            foreach ($data->ids as $id) {
                if (!is_numeric($id)) {
                    throw new BadRequestHttpException('Incorrect id data.');
                }
            }
        }
        if (!property_exists($data, 'managements')
            || !is_array($data->managements)
        ) {
            throw new BadRequestHttpException('Incorrect managements data.');
        }
        foreach ($data->managements as $management) {
            if (!is_object($management)
                || !property_exists($management, 'id')
                || !is_numeric($management->id)
            ) {
                throw new BadRequestHttpException('Incorrect management data.');
            }
        }

        $entities = $this->getShort($data->ids);

        $delIds = [];
        foreach ($entities as $entity) {
            foreach ($data->managements as $newMan) {
                $exists = false;

                foreach ($entity->getManagements() as $oldMan) {
                    if ($newMan->id == $oldMan->getId()) {
                        $exists = true;
                        break;
                    }
                }

                if ($exists) {
                    if (!isset($delIds[$entity->getId()])) {
                        $delIds[$entity->getId()] = [];
                    }
                    $delIds[$entity->getId()][] = $newMan->id;
                }
            }
        }

        // Update entities
        if (!empty($delIds)) {
            $this->dbs->beginTransaction();
            try {
                // update management collections
                foreach ($delIds as $entityId => $ids) {
                    $this->dbs->delManagements($entityId, $ids);
                }

                // update log with minimal data (id, managements)
                $rawManagements = $this->dbs->getManagements(array_keys($delIds));
                $newEntities = [];
                foreach (array_keys($delIds) as $id) {
                    $newEntities[$id] = (new Entity())
                        ->setId($id);
                }
                if (!empty($rawManagements)) {
                    $managements = $this->container->get('management_manager')->getWithData($rawManagements);

                    foreach ($rawManagements as $rawManagement) {
                        $newEntities[$rawManagement['entity_id']]
                            ->addManagement($managements[$rawManagement['management_id']]);
                    }
                }

                foreach ($newEntities as $newEntity) {
                    $old = (new Entity())
                        ->setId($newEntity->getId())
                        ->setManagements($entities[$newEntity->getId()]->getManagements());
                    $this->updateModified($old, $newEntity);
                }

                // update data to update elastic search
                $esData = [];
                foreach ($newEntities as $newEntity) {
                    $esData[$newEntity->getId()] = [
                        'id' => $newEntity->getId(),
                        'management' => ArrayToJson::arrayToShortJson($newEntity->getManagements()),
                    ];
                }
                $this->ess->updateMultiple($esData);

                // commit transaction
                $this->dbs->commit();
            } catch (\Exception $e) {
                $this->dbs->rollBack();

                throw $e;
            }
        }
    }

    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // remove from elasticsearch
            $this->updateElasticByIds([$id]);

            // commit transaction
            $this->dbs->commit();
        } catch (DependencyException $e) {
            $this->dbs->rollBack();
            throw new BadRequestHttpException($e->getMessage());
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return;
    }

    private static function certainString(stdClass $object, string $property): string
    {
        if (!property_exists($object, $property)) {
            return '';
        }
        if (empty($object->$property)) {
            return '';
        }
        return $object->$property;
    }
}
