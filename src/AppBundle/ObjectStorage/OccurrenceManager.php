<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Genre;
use AppBundle\Model\Image;
use AppBundle\Model\Meter;
use AppBundle\Model\Status;
use AppBundle\Model\Occurrence;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * ObjectManager for occurrences
 * Servicename: occurrence_manager
 */
class OccurrenceManager extends DocumentManager
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

                $rawIncipits = $this->dbs->getIncipits($ids);
                if (count($rawIncipits) > 0) {
                    foreach ($rawIncipits as $rawIncipit) {
                        $occurrences[$rawIncipit['occurrence_id']]
                            ->setIncipit($rawIncipit['incipit']);
                    }
                }

                // number of verses
                $rawVerses = $this->dbs->getNumberOfVerses($ids);
                if (count($rawVerses) > 0) {
                    foreach ($rawVerses as $rawVerse) {
                        $occurrences[$rawVerse['occurrence_id']]
                            ->setNumberOfVerses($rawVerse['verses']);
                    }
                }

                // Verses (needed in mini to calculate number of verses correctly)
                $rawVerses = $this->dbs->getVerses($ids);
                $verses = $this->container->get('verse_manager')->getMiniWithData($rawVerses);
                foreach ($rawVerses as $rawVerse) {
                    $occurrences[$rawVerse['occurrence_id']]->addVerse($verses[$rawVerse['verse_id']]);
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

                // Title
                $rawTitles = $this->dbs->getTitles($ids);
                foreach ($rawTitles as $rawTitle) {
                    $occurrences[$rawTitle['occurrence_id']]
                        ->setTitle($rawTitle['title']);
                }

                // Meter
                $rawMeters = $this->dbs->getMeters($ids);
                foreach ($rawMeters as $rawMeter) {
                    // TODO: getwithdata meter
                    $occurrences[$rawMeter['occurrence_id']]
                        ->setMeter(new Meter($rawMeter['meter_id'], $rawMeter['meter_name']));
                }

                // Subject
                $rawSubjects = $this->dbs->getSubjects($ids);
                $personIds = self::getUniqueIds($rawSubjects, 'person_id');
                $persons = [];
                if (count($personIds) > 0) {
                    $persons = $this->container->get('person_manager')->getShort($personIds);
                }
                $keywordIds = self::getUniqueIds($rawSubjects, 'keyword_id');
                $keywords = [];
                if (count($keywordIds) > 0) {
                    $keywords = $this->container->get('keyword_manager')->get($keywordIds);
                }
                foreach ($rawSubjects as $rawSubject) {
                    if (isset($rawSubject['person_id'])) {
                        $occurrences[$rawSubject['occurrence_id']]->addSubject($persons[$rawSubject['person_id']]);
                    } elseif (isset($rawSubject['keyword_id'])) {
                        $occurrences[$rawSubject['occurrence_id']]->addSubject($keywords[$rawSubject['keyword_id']]);
                    }
                }

                $this->setPersonRoles($occurrences);

                $this->setDates($occurrences);

                // Genre
                $rawGenres = $this->dbs->getGenres($ids);
                foreach ($rawGenres as $rawGenre) {
                    $occurrences[$rawGenre['occurrence_id']]
                        ->setGenre(new Genre($rawGenre['genre_id'], $rawGenre['genre_name']));
                }

                $this->setComments($occurrences);

                // text and record status
                $rawStatuses = $this->dbs->getStatuses($ids);
                foreach ($rawStatuses as $rawStatus) {
                    if ($rawStatus['type'] == 'occurrence_text') {
                        $occurrences[$rawStatus['occurrence_id']]
                            ->setTextStatus(new Status($rawStatus['status_id'], $rawStatus['status_name']));
                    }
                    if ($rawStatus['type'] == 'occurrence_record') {
                        $occurrences[$rawStatus['occurrence_id']]
                            ->setRecordStatus(new Status($rawStatus['status_id'], $rawStatus['status_name']));
                    }
                }

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
                foreach ($rawImages as $rawImage) {
                    if (strpos($rawImage['url'], 'http') === 0) {
                        $occurrence
                            ->addImageLink(new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']));
                    } elseif (strpos($rawImage['url'], 'png') === false
                        && strpos($rawImage['url'], 'jpg') === false
                        && strpos($rawImage['url'], 'JPG') === false
                ) {
                        $occurrence
                            ->addImageText(new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']));
                    } else {
                        $occurrence
                            ->addImage(new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']));
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

    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short ? 'getShort' : 'getMini');
    }

    public function getMeterDependencies(int $meterId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByMeterId($meterId), $short ? 'getShort' : 'getMini');
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
            if (property_exists($data, 'meter')) {
                if (!(empty($data->meter) || is_object($data->meter))) {
                    throw new BadRequestHttpException('Incorrect meter data.');
                }
                $cacheReload['short'] = true;
                $this->updateMeter($old, $data->meter);
            }

            // TODO: other information

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

    private function updateMeter(Occurrence $occurrence, stdClass $meter = null): void
    {
        if (empty($meter)) {
            if (!empty($occurrence->getMeter())) {
                $this->dbs->deleteMeter($occurrence->getId());
            }
        } elseif (!property_exists($meter, 'id') || !is_numeric($meter->id)) {
            throw new BadRequestHttpException('Incorrect meter data.');
        } else {
            if (empty($occurrence->getMeter())) {
                $this->dbs->insertMeter($occurrence->getId(), $meter->id);
            } else {
                $this->dbs->updateMeter($occurrence->getId(), $meter->id);
            }
        }
    }

    // TODO: delete
    // also delete verses
}
