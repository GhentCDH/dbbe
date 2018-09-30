<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Status;
use AppBundle\Model\Occurrence;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * ObjectManager for occurrences
 * Servicename: occurrence_manager
 */
class OccurrenceManager extends PoemManager
{
    /**
     * Get occurrences with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Occurrence::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $occurrences = [];
                $rawLocations = $this->dbs->getLocations($ids);
                if (count($rawLocations) == 0) {
                    return [];
                }
                foreach ($rawLocations as $rawLocation) {
                    $occurrences[$rawLocation['occurrence_id']] = (new Occurrence())
                        ->setId($rawLocation['occurrence_id'])
                        ->setFoliumStart($rawLocation['folium_start'])
                        ->setFoliumStartRecto($rawLocation['folium_start_recto'])
                        ->setFoliumEnd($rawLocation['folium_end'])
                        ->setFoliumEndRecto($rawLocation['folium_end_recto'])
                        ->setUnsure($rawLocation['unsure'])
                        ->setGeneralLocation($rawLocation['general_location'])
                        ->setAlternativeFoliumStart($rawLocation['alternative_folium_start'])
                        ->setAlternativeFoliumStartRecto($rawLocation['alternative_folium_start_recto'])
                        ->setAlternativeFoliumEnd($rawLocation['alternative_folium_end'])
                        ->setAlternativeFoliumEndRecto($rawLocation['alternative_folium_end_recto']);
                }

                // Remove all ids that did not match above
                $ids = array_keys($occurrences);

                $this->setIncipits($occurrences);

                // number of verses
                $rawNumbersOfVerses = $this->dbs->getNumberOfVerses($ids);
                if (count($rawNumbersOfVerses) > 0) {
                    foreach ($rawNumbersOfVerses as $rawNumberOfVerses) {
                        $occurrences[$rawNumberOfVerses['occurrence_id']]
                            ->setNumberOfVerses($rawNumberOfVerses['verses']);
                    }
                }

                // Verses (needed in mini to calculate number of verses)
                $rawVerses = $this->dbs->getVerses($ids);
                $verses = $this->container->get('verse_manager')->getMiniWithData($rawVerses);
                foreach ($rawVerses as $rawVerse) {
                    $occurrences[$rawVerse['occurrence_id']]
                        ->addVerse($verses[$rawVerse['verse_id']]);
                }

                $this->setPublics($occurrences);

                return $occurrences;
            }
        );
    }

    /**
     * Get occurrences with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->wrapLevelCache(
            Occurrence::CACHENAME,
            'short',
            $ids,
            function ($ids) {
                $occurrences = $this->getMini($ids);

                // Remove all ids that did not match above
                $ids = array_keys($occurrences);

                // Manuscript
                $rawLocations = $this->dbs->getLocations($ids);
                if (count($rawLocations) == 0) {
                    return [];
                }
                $manuscriptIds = self::getUniqueIds($rawLocations, 'manuscript_id');
                $manuscripts = $this->container->get('manuscript_manager')->getShort($manuscriptIds);
                foreach ($rawLocations as $rawLocation) {
                    if (isset($manuscripts[$rawLocation['manuscript_id']])) {
                        $manuscript = $manuscripts[$rawLocation['manuscript_id']];
                        $occurrences[$rawLocation['occurrence_id']]->setManuscript($manuscript);
                    }
                }

                $this->setTitles($occurrences);

                $this->setMeters($occurrences);

                $this->setSubjects($occurrences);

                $this->setPersonRoles($occurrences);

                $this->setDates($occurrences);

                $this->setGenres($occurrences);

                $this->setComments($occurrences);

                // statuses
                $rawStatuses = $this->dbs->getStatuses($ids);
                $statuses = $this->container->get('status_manager')->getWithData($rawStatuses);
                foreach ($rawStatuses as $rawStatus) {
                    switch ($rawStatus['status_type']) {
                        case Status::OCCURRENCE_TEXT:
                            $occurrences[$rawStatus['occurrence_id']]
                                ->setTextStatus($statuses[$rawStatus['status_id']]);
                            break;
                        case Status::OCCURRENCE_RECORD:
                            $occurrences[$rawStatus['occurrence_id']]
                                ->setRecordStatus($statuses[$rawStatus['status_id']]);
                            break;
                        case Status::OCCURRENCE_DIVIDED:
                            $occurrences[$rawStatus['occurrence_id']]
                                ->setDividedStatus($statuses[$rawStatus['status_id']]);
                            break;
                        case Status::OCCURRENCE_SOURCE:
                            $occurrences[$rawStatus['occurrence_id']]
                                ->setSourceStatus($statuses[$rawStatus['status_id']]);
                            break;
                    }
                }

                $this->setAcknowledgements($occurrences);

                // Needed to index DBBE in elasticsearch
                $this->setBibliographies($occurrences);

                return $occurrences;
            }
        );
    }

    /**
     * Get a single occurrence with all information
     * @param  int         $id
     * @return Occurrence
     */
    public function getFull(int $id): Occurrence
    {
        return $this->wrapSingleLevelCache(
            Occurrence::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic occurrence information
                $occurrences = $this->getShort([$id]);
                if (count($occurrences) == 0) {
                    throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
                }

                $this->setIdentifications($occurrences);

                $this->setPrevIds($occurrences);

                $occurrence = $occurrences[$id];

                // related occurrences
                $rawRelOccurrences = $this->dbs->getRelatedOccurrences([$id]);
                if (!empty($rawRelOccurrences)) {
                    $relOccurrenceIds = self::getUniqueIds($rawRelOccurrences, 'related_occurrence_id');
                    $relOccurrences = $this->getMini($relOccurrenceIds);
                    foreach ($rawRelOccurrences as $rawRelOccurrence) {
                        $occurrence->addRelatedOccurrence(
                            $relOccurrences[$rawRelOccurrence['related_occurrence_id']],
                            $rawRelOccurrence['count']
                        );
                    }
                }

                // types
                $rawTypes = $this->dbs->getTypes([$id]);
                if (!empty($rawTypes)) {
                    $typeIds = self::getUniqueIds($rawTypes, 'type_id');
                    $types =  $this->container->get('type_manager')->getMini($typeIds);
                    $occurrence->setTypes($types);
                }

                // paleographical information
                $rawPaleographicalInfos = $this->dbs->getPaleographicalInfos([$id]);
                if (count($rawPaleographicalInfos) == 1) {
                    $occurrence
                        ->setPaleographicalInfo($rawPaleographicalInfos[0]['paleographical_info']);
                }

                // contextual information
                $rawContextualInfos = $this->dbs->getContextualInfos([$id]);
                if (count($rawContextualInfos) == 1) {
                    $occurrence
                        ->setContextualInfo($rawContextualInfos[0]['contextual_info']);
                }

                // images
                $rawImages = $this->dbs->getImages([$id]);
                $images = $this->container->get('image_manager')->getWithData($rawImages);
                foreach ($rawImages as $rawImage) {
                    if (!empty($rawImage['filename'])) {
                        $occurrence
                            ->addImage($images[$rawImage['image_id']]);
                    } else {
                        $occurrence
                            ->addImageLink($images[$rawImage['image_id']]);
                    }
                }

                return $occurrence;
            }
        );
    }

    public function getManuscriptDependencies(int $manuscriptId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByManuscriptId($manuscriptId), $short ? 'getShort' : 'getMini');
    }

    public function add(stdClass $data): Occurrence
    {
        // Incipit and manuscript are required fields
        if (!property_exists($data, 'incipit')
            || !is_string($data->incipit)
            || empty($data->incipit)
            || !property_exists($data, 'manuscript')
            || !is_object($data->manuscript)
            || !property_exists($data->manuscript, 'id')
            || !is_numeric($data->manuscript->id)
            || empty($data->manuscript->id)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new occurrence.');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->manuscript->id);

            // Clear manuscript cache so occurrences will be reloaded
            $this->container->get('manuscript_manager')->clearCache($data->manuscript->id, ['short' => true]);

            unset($data->manuscript);

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // manuscript data is not loaded here, so it does not need to be reset

            throw $e;
        }

        return $new;
    }

    public function update(int $id, stdClass $data, bool $isNew = false): Occurrence
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
            }

            $cacheReload = [
                'mini' => $isNew,
                'short' => $isNew,
                'full' => $isNew,
            ];
            if (property_exists($data, 'incipit')) {
                // Incipit is a required field
                if (!is_string($data->incipit)
                    || empty($data->incipit)
                ) {
                    throw new BadRequestHttpException('Incorrect incipit data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateIncipit($id, $data->incipit);
            }
            if (property_exists($data, 'title')) {
                if (!is_string($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }

                $cacheReload['short'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            if (property_exists($data, 'manuscript')) {
                // Manuscript is a required field
                if (!is_object($data->manuscript)
                    || !property_exists($data->manuscript, 'id')
                    || !is_numeric($data->manuscript->id)
                    || empty($data->manuscript->id)
                ) {
                    throw new BadRequestHttpException('Incorrect manuscript data.');
                }

                $cacheReload['short'] = true;
                $this->dbs->updateManuscript($id, $data->manuscript->id);
                // Reset old and new manuscript
                $this->container->get('manuscript_manager')->reset([
                    $old->getManuscript()->getId(),
                    $data->manuscript->id,
                ]);
            }
            if (property_exists($data, 'foliumStart')) {
                if (!is_string($data->foliumStart)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumStart data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateFoliumStart($id, $data->foliumStart);
            }
            if (property_exists($data, 'foliumStartRecto')) {
                if (!is_bool($data->foliumStartRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumStartRecto data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateFoliumStartRecto($id, $data->foliumStartRecto);
            }
            if (property_exists($data, 'foliumEnd')) {
                if (!is_string($data->foliumEnd)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumEnd data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateFoliumEnd($id, $data->foliumEnd);
            }
            if (property_exists($data, 'foliumEndRecto')) {
                if (!is_bool($data->foliumEndRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumEndRecto data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateFoliumEndRecto($id, $data->foliumEndRecto);
            }
            if (property_exists($data, 'unsure')) {
                if (!is_bool($data->unsure)
                ) {
                    throw new BadRequestHttpException('Incorrect unsure data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateUnsure($id, $data->unsure);
            }
            if (property_exists($data, 'generalLocation')) {
                if (!is_string($data->generalLocation)
                ) {
                    throw new BadRequestHttpException('Incorrect generalLocation data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateGeneralLocation($id, $data->generalLocation);
            }
            if (property_exists($data, 'alternativeFoliumStart')) {
                if (!is_string($data->alternativeFoliumStart)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumStart data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateAlternativeFoliumStart($id, $data->alternativeFoliumStart);
            }
            if (property_exists($data, 'alternativeFoliumStartRecto')) {
                if (!is_bool($data->alternativeFoliumStartRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumStartRecto data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateAlternativeFoliumStartRecto($id, $data->alternativeFoliumStartRecto);
            }
            if (property_exists($data, 'alternativeFoliumEnd')) {
                if (!is_string($data->alternativeFoliumEnd)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumEnd data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateAlternativeFoliumEnd($id, $data->alternativeFoliumEnd);
            }
            if (property_exists($data, 'alternativeFoliumEndRecto')) {
                if (!is_bool($data->alternativeFoliumEndRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumEndRecto data.');
                }

                $cacheReload['mini'] = true;
                $this->dbs->updateAlternativeFoliumEndRecto($id, $data->alternativeFoliumEndRecto);
            }
            if (property_exists($data, 'numberOfVerses')) {
                if (!is_numeric($data->numberOfVerses)) {
                    throw new BadRequestHttpException('Incorrect verses data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateNumberOfVerses($id, $data->numberOfVerses);
            }
            if (property_exists($data, 'verses')) {
                if (!is_array($data->verses)) {
                    throw new BadRequestHttpException('Incorrect verses data.');
                }
                $cacheReload['mini'] = true;
                $touched = [];
                $verseIds = $this->updateVerses($old, $data->verses, $touched);
            }
            if (property_exists($data, 'types')) {
                if (!is_array($data->types)) {
                    throw new BadRequestHttpException('Incorrect types data.');
                }
                $cacheReload['full'] = true;
                $this->updateTypes($old, $data->types);
            }
            $roles = $this->container->get('role_manager')->getRolesByType('occurrence');
            $personUpdate = false;
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $cacheReload['short'] = true;
                    $personUpdate = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            // Update manuscript roles
            if ($personUpdate) {
                $manuscriptId = $old->getManuscript()->getId();
                if (isset($data->manuscript)) {
                    $manuscriptId = $data->manuscript->id;
                }
                $this->container->get('manuscript_manager')->elasticIndexByIds([$manuscriptId]);
            }
            if (property_exists($data, 'meters')) {
                if (!is_array($data->meters)) {
                    throw new BadRequestHttpException('Incorrect meter data.');
                }
                $cacheReload['short'] = true;
                $this->updateMeters($old, $data->meters);
            }
            if (property_exists($data, 'genres')) {
                if (!is_array($data->genres)) {
                    throw new BadRequestHttpException('Incorrect genre data.');
                }
                $cacheReload['short'] = true;
                $this->updateGenres($old, $data->genres);
            }
            if (property_exists($data, 'persons')) {
                if (!is_array($data->persons)) {
                    throw new BadRequestHttpException('Incorrect person subject data.');
                }
                $cacheReload['short'] = true;
                $this->updatePersonSubjects($old, $data->persons);
            }
            if (property_exists($data, 'keywords')) {
                if (!is_array($data->keywords)) {
                    throw new BadRequestHttpException('Incorrect keyword subject data.');
                }
                $cacheReload['short'] = true;
                $this->updateKeywordSubjects($old, $data->keywords);
            }
            $this->updateIdentificationwrapper($old, $data, $cacheReload, 'full', 'occurrence');
            if (property_exists($data, 'bibliography')) {
                if (!is_object($data->bibliography)) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                // short is needed here to index DBBE in elasticsearch
                $cacheReload['short'] = true;
                $this->updateBibliography($old, $data->bibliography, true);
            }
            if (property_exists($data, 'paleographicalInfo')) {
                if (!is_string($data->paleographicalInfo)) {
                    throw new BadRequestHttpException('Incorrect paleographical information data.');
                }
                $cacheReload['full'] = true;
                $this->dbs->updatePaleographicalInfo($id, $data->paleographicalInfo);
            }
            if (property_exists($data, 'contextualInfo')) {
                if (!is_string($data->contextualInfo)) {
                    throw new BadRequestHttpException('Incorrect contextual information data.');
                }
                $cacheReload['full'] = true;
                $this->dbs->updateContextualInfo($id, $data->contextualInfo);
            }
            if (property_exists($data, 'acknowledgements')) {
                if (!is_array($data->acknowledgements)) {
                    throw new BadRequestHttpException('Incorrect acknowledgements data.');
                }
                $cacheReload['short'] = true;
                $this->updateAcknowledgements($old, $data->acknowledgements);
            }
            if (property_exists($data, 'recordStatus')) {
                if (!(is_object($data->recordStatus) || empty($data->recordStatus))) {
                    throw new BadRequestHttpException('Incorrect record status data.');
                }
                $cacheReload['short'] = true;
                $this->updateStatus($old, $data->recordStatus, Status::OCCURRENCE_RECORD);
            }
            if (property_exists($data, 'textStatus')) {
                if (!(is_object($data->textStatus) || empty($data->textStatus))) {
                    throw new BadRequestHttpException('Incorrect text status data.');
                }
                $cacheReload['short'] = true;
                $this->updateStatus($old, $data->textStatus, Status::OCCURRENCE_TEXT);
            }
            if (property_exists($data, 'dividedStatus')) {
                if (!(is_object($data->dividedStatus) || empty($data->dividedStatus))) {
                    throw new BadRequestHttpException('Incorrect divided status data.');
                }
                $cacheReload['short'] = true;
                $this->updateStatus($old, $data->dividedStatus, Status::OCCURRENCE_DIVIDED);
            }
            if (property_exists($data, 'sourceStatus')) {
                if (!(is_object($data->sourceStatus) || empty($data->sourceStatus))) {
                    throw new BadRequestHttpException('Incorrect source status data.');
                }
                $cacheReload['short'] = true;
                $this->updateStatus($old, $data->sourceStatus, Status::OCCURRENCE_SOURCE);
            }
            if (property_exists($data, 'images')) {
                if (!(is_array($data->images))) {
                    throw new BadRequestHttpException('Incorrect images data.');
                }
                $cacheReload['full'] = true;
                $this->updateImages($old, $data->images);
            }
            if (property_exists($data, 'imageLinks')) {
                if (!(is_array($data->imageLinks))) {
                    throw new BadRequestHttpException('Incorrect image links data.');
                }
                $cacheReload['full'] = true;
                $this->updateImageLinks($old, $data->imageLinks);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            if (!$isNew) {
                $this->clearCache($id, $cacheReload);
            }
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            // (re-)index in elastic search
            if ($cacheReload['mini'] || $cacheReload['short']) {
                $this->ess->add($new);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // Reset cache and elasticsearch on elasticsearch error
            if (isset($new)) {
                $this->reset([$id]);
            }

            // Reset manuscripts (potentially old and new)
            $manuscriptIds = [$old->getManuscript()->getId()];
            if (isset($data->manuscript)) {
                $manuscriptIds[] = $data->manuscript->id;
            }
            $this->container->get('manuscript_manager')->reset($manuscriptIds);

            // Reset verses
            if (isset($touched)) {
                $this->container->get('verse_manager')->reset($touched);
            }

            throw $e;
        }

        return $new;
    }

    public function updateVerses(Occurrence $occurrence, array $verses, array &$touched)
    {
        foreach ($verses as $verse) {
            if (!is_object($verse)
                || !property_exists($verse, 'verse')
                || !is_string($verse->verse)
                || (
                    property_exists($verse, 'id')
                    && !is_numeric($verse->id)
                )
            ) {
                throw new BadRequestHttpException('Incorrect verses data.');
            }
        }

        $oldVerses = $occurrence->getVerses();
        $ids = [];
        foreach ($verses as $order => $verse) {
            if (!property_exists($verse, 'id')) {
                // new verses
                $verse->occurrence = json_decode(json_encode(['id' => $occurrence->getId()]));
                $verse->order = $order;
                $newVerse = $this->container->get('verse_manager')->add($verse);
                $touched[] = $newVerse->getId();
            } else {
                // old verses
                $oldVerseFilter = array_filter(
                    $oldVerses,
                    function ($oldVerse) use ($verse) {
                        return $oldVerse->getId() == $verse->id;
                    }
                );
                if (count($oldVerseFilter) != 1) {
                    throw new BadRequestHttpException('Incorrect verses data.');
                }
                $ids[] = $verse->id;
                $oldVerse = $oldVerseFilter[$verse->id];
                $data = [];
                if ($oldVerse->getOrder() != $order) {
                    $data['order'] = $order;
                }
                if ($oldVerse->getVerse() != $verse->verse) {
                    $data['verse'] = $verse->verse;
                }
                if (property_exists($verse, 'linkVerses')) {
                    if (!is_array($verse->linkVerses)
                    ) {
                        throw new BadRequestHttpException('Incorrect linkVerses data.');
                    }
                    foreach ($verse->linkVerses as $linkVerse) {
                        if (!is_object($linkVerse)
                            ||!property_exists($linkVerse, 'id')
                            ||!is_numeric($linkVerse->id)
                        ) {
                            throw new BadRequestHttpException('Incorrect linkVerses data.');
                        }
                    }
                    $data['linkVerses'] = $verse->linkVerses;
                } elseif (property_exists($verse, 'groupId')
                    && $verse->groupId == null
                    && $oldVerse->getGroupId() != null
                ) {
                    // Remove existing links
                    $data['groupId'] = null;
                }
                if (!empty($data)) {
                    $this->container->get('verse_manager')->update($verse->id, json_decode(json_encode($data)));
                    $touched[] = $verse->id;
                    if (property_exists($verse, 'linkVerses')) {
                        foreach ($verse->linkVerses as $linkVerse) {
                            $touched[] = $linkVerse->id;
                        }
                    }
                }
            }
        }

        // deleted verses
        foreach ($oldVerses as $oldVerse) {
            if (!in_array($oldVerse->getId(), $ids)) {
                $this->container->get('verse_manager')->delete($oldVerse->getId());
                $touched[] = $oldVerse->getId();
            }
        }
    }

    private function updateTypes(Occurrence $occurrence, array $types): void
    {
        foreach ($types as $type) {
            if (!is_object($type)
                || !property_exists($type, 'id')
                || !is_numeric($type->id)
            ) {
                throw new BadRequestHttpException('Incorrect content data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($types, $occurrence->getTypes());

        if (count($delIds) > 0) {
            $this->dbs->delTypes($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addType($occurrence->getId(), $addId);
        }
    }

    private function updateMeters(Occurrence $occurrence, array $meters): void
    {
        foreach ($meters as $meter) {
            if (!is_object($meter)
                || !property_exists($meter, 'id')
                || !is_numeric($meter->id)
            ) {
                throw new BadRequestHttpException('Incorrect meter data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($meters, $occurrence->getMeters());

        if (count($delIds) > 0) {
            $this->dbs->delMeters($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addMeter($occurrence->getId(), $addId);
        }
    }

    private function updateGenres(Occurrence $occurrence, array $genres): void
    {
        foreach ($genres as $genre) {
            if (!is_object($genre)
                || !property_exists($genre, 'id')
                || !is_numeric($genre->id)
            ) {
                throw new BadRequestHttpException('Incorrect genre data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($genres, $occurrence->getGenres());

        if (count($delIds) > 0) {
            $this->dbs->delGenres($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addGenre($occurrence->getId(), $addId);
        }
    }

    private function updatePersonSubjects(Occurrence $occurrence, array $persons): void
    {
        foreach ($persons as $person) {
            if (!is_object($person)
                || !property_exists($person, 'id')
                || !is_numeric($person->id)
            ) {
                throw new BadRequestHttpException('Incorrect person subject data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($persons, $occurrence->getPersonSubjects());

        if (count($delIds) > 0) {
            $this->dbs->delSubjects($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addSubject($occurrence->getId(), $addId);
        }
    }

    private function updateKeywordSubjects(Occurrence $occurrence, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            if (!is_object($keyword)
                || !property_exists($keyword, 'id')
                || !is_numeric($keyword->id)
            ) {
                throw new BadRequestHttpException('Incorrect keyword subject data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($keywords, $occurrence->getKeywordSubjects());

        if (count($delIds) > 0) {
            $this->dbs->delSubjects($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addSubject($occurrence->getId(), $addId);
        }
    }

    private function updateImages(Occurrence $occurrence, array $images): void
    {
        $newIds = [];
        foreach ($images as $image) {
            if (!is_object($image)
                || !property_exists($image, 'id')
                || !is_numeric($image->id)
                || (property_exists($image, 'public') && !is_bool($image->public))
            ) {
                throw new BadRequestHttpException('Incorrect image data.');
            } else {
                $newIds[] = $image->id;
            }
        }

        list($delIds, $addIds) = self::calcDiff($images, $occurrence->getImages());

        if (count($delIds) > 0) {
            $this->dbs->delImages($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addImage($occurrence->getId(), $addId);
        }

        // Update public state if necessary
        $oldImages = $this->container->get('image_manager')->get($newIds);
        foreach ($images as $image) {
            if (property_exists($image, 'public') && $image->public != $oldImages[$image->id]->getPublic()) {
                $this->container->get('image_manager')->update(
                    $image->id,
                    json_decode(json_encode(['public' => $image->public]))
                );
            }
        }
    }

    private function updateImageLinks(Occurrence $occurrence, array $imageLinks): void
    {
        $updateIds = [];
        $updateLinks = [];
        $newLinks = [];
        foreach ($imageLinks as $link) {
            if (!is_object($link)
                || (property_exists($link, 'id') && !is_numeric($link->id))
                || (property_exists($link, 'url') && !is_string($link->url))
                || (property_exists($link, 'public') && !is_bool($link->public))
                // If it is a new imageLink, both url and public are mandatory
                || (
                    !property_exists($link, 'id')
                    && (
                        !property_exists($link, 'url')
                        || empty($link->url)
                        || !property_exists($link, 'public')
                    )
                )
            ) {
                throw new BadRequestHttpException('Incorrect image link data.');
            } elseif (property_exists($link, 'id')) {
                $updateIds[] = $link->id;
                $updateLinks[] = $link;
            } else {
                $newLinks[] = $link;
            }
        }

        // Remove and add existing image links
        list($delIds, $addIds) = self::calcDiff($updateLinks, $occurrence->getImageLinks());

        if (count($delIds) > 0) {
            $this->dbs->delImages($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addImage($occurrence->getId(), $addId);
        }

        // Update url and public state if necessary
        $oldImageLinks = $this->container->get('image_manager')->get($updateIds);
        foreach ($updateLinks as $link) {
            $data = [];
            if (property_exists($link, 'public') && $link->public != $oldImageLinks[$link->id]->getPublic()) {
                $data['public'] = $link->public;
            }
            if (property_exists($link, 'url') && $link->url != $oldImageLinks[$link->id]->getPublic()) {
                $data['url'] = $link->url;
            }
            if (!empty($data)) {
                $this->container->get('image_manager')->update(
                    $link->id,
                    json_decode(json_encode($data))
                );
            }
        }

        // Add new image links
        foreach ($newLinks as $link) {
            $imageLink = $this->container->get('image_manager')->add($link);
            $this->dbs->addImage($occurrence->getId(), $imageLink->getId());
        }
    }

    private function updateAcknowledgements(Occurrence $occurrence, array $acknowledgements): void
    {
        foreach ($acknowledgements as $acknowledgement) {
            if (!is_object($acknowledgement)
                || !property_exists($acknowledgement, 'id')
                || !is_numeric($acknowledgement->id)
            ) {
                throw new BadRequestHttpException('Incorrect acknowledgement data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($acknowledgements, $occurrence->getAcknowledgements());

        if (count($delIds) > 0) {
            $this->dbs->delAcknowledgements($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addAcknowledgement($occurrence->getId(), $addId);
        }
    }

    // TODO: delete
    // also delete verses
}
