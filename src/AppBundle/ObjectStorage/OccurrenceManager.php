<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Genre;
use AppBundle\Model\Image;
use AppBundle\Model\Meter;
use AppBundle\Model\Status;
use AppBundle\Model\Occurrence;

class OccurrenceManager extends DocumentManager
{
    /**
     * Get occurrences with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMiniOccurrencesByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'occurrence_mini');
        if (empty($ids)) {
            return $cached;
        }

        $occurrences = [];
        $rawLocations = $this->dbs->getLocations($ids);
        if (count($rawLocations) == 0) {
            return $cached;
        }
        foreach ($rawLocations as $rawLocation) {
            $occurrences[$rawLocation['occurrence_id']] = (new Occurrence())
                ->setId($rawLocation['occurrence_id'])
                ->setFoliumStart($rawLocation['folium_start'])
                ->setFoliumStartRecto($rawLocation['folium_start_recto'])
                ->setFoliumEnd($rawLocation['folium_end'])
                ->setFoliumEndRecto($rawLocation['folium_end_recto'])
                ->setGeneralLocation($rawLocation['general_location']);
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

        $this->setPublics($occurrences);

        $this->setCache($occurrences, 'occurrence_mini');

        return $cached + $occurrences;
    }

    public function getShortOccurrencesByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'occurrence_short');
        if (empty($ids)) {
            return $cached;
        }

        $occurrences = $this->getMiniOccurrencesByIds($ids);

        // Remove all ids that did not match above
        $ids = array_keys($occurrences);

        // Manuscript
        $rawLocations = $this->dbs->getLocations($ids);
        if (count($rawLocations) == 0) {
            return $cached;
        }
        $manuscriptIds = self::getUniqueIds($rawLocations, 'manuscript_id');
        $manuscripts = $this->container->get('manuscript_manager')->getShortManuscriptsByIds($manuscriptIds);
        foreach ($rawLocations as $rawLocation) {
            if (isset($manuscripts[$rawLocation['manuscript_id']])) {
                $manuscript = $manuscripts[$rawLocation['manuscript_id']];
                $occurrences[$rawLocation['occurrence_id']]
                    ->setManuscript($manuscript);
                foreach ($manuscript->getCacheDependencies() as $cacheDependency) {
                    // prevent self reference
                    if ($cacheDependency != 'occurrence.' . $rawLocation['occurrence_id']) {
                        $occurrences[$rawLocation['occurrence_id']]
                            ->addCacheDependency($cacheDependency);
                    }
                }
            }
        }

        // Title
        $rawTitles = $this->dbs->getTitles($ids);
        foreach ($rawTitles as $rawTitle) {
            $occurrences[$rawTitle['occurrence_id']]
                ->setTitle($rawTitle['title']);
        }

        // Text
        $rawTexts = $this->dbs->getTexts($ids);
        foreach ($rawTexts as $rawText) {
            $occurrences[$rawText['occurrence_id']]
                ->setText($rawText['text_content']);
        }

        // Meter
        $rawMeters = $this->dbs->getMeters($ids);
        foreach ($rawMeters as $rawMeter) {
            $occurrences[$rawMeter['occurrence_id']]
                ->setMeter(new Meter($rawMeter['meter_id'], $rawMeter['meter_name']))
                ->addCacheDependency('meter.' . $rawMeter['meter_id']);
        }

        // Subject
        $rawSubjects = $this->dbs->getSubjects($ids);
        $personIds = self::getUniqueIds($rawSubjects, 'person_id');
        $persons = [];
        if (count($personIds) > 0) {
            $persons = $this->container->get('person_manager')->getShortPersonsByIds($personIds);
        }
        $keywordIds = self::getUniqueIds($rawSubjects, 'keyword_id');
        $keywords = [];
        if (count($keywordIds) > 0) {
            $keywords = $this->container->get('keyword_manager')->getKeywordsByIds($keywordIds);
        }
        foreach ($rawSubjects as $rawSubject) {
            if (isset($rawSubject['person_id'])) {
                foreach ($persons[$rawSubject['person_id']]->getCacheDependencies() as $cacheDependency) {
                    $occurrences[$rawSubject['occurrence_id']]
                        ->addCacheDependency($cacheDependency);
                }
                $occurrences[$rawSubject['occurrence_id']]
                    ->addSubject($persons[$rawSubject['person_id']])
                    ->addCacheDependency('person_short.' . $rawSubject['person_id']);
                foreach ($persons[$rawSubject['person_id']]->getCacheDependencies() as $cacheDependency) {
                    $occurrences[$rawSubject['occurrence_id']]
                        ->addCacheDependency($cacheDependency);
                }
            } elseif (isset($rawSubject['keyword_id'])) {
                $occurrences[$rawSubject['occurrence_id']]
                    ->addSubject($keywords[$rawSubject['keyword_id']])
                    ->addCacheDependency('keyword.' . $rawSubject['keyword_id']);
            }
        }

        // Patrons and scribes
        // Bundle to reduce number of database requests
        $rawBibroles = $this->dbs->getBibroles($ids, ['patron', 'scribe']);
        $patronIds = self::getUniqueIds($rawBibroles, 'person_id', 'type', 'patron');
        $scribeIds = self::getUniqueIds($rawBibroles, 'person_id', 'type', 'scribe');
        $personIds = array_unique(array_merge($patronIds, $scribeIds));
        $persons = [];
        if (count($personIds) > 0) {
            $persons = $this->container->get('person_manager')->getShortPersonsByIds($personIds);
        }
        foreach ($rawBibroles as $rawBibrole) {
            $person = $persons[$rawBibrole['person_id']];
            if ($rawBibrole['type'] == 'patron') {
                $occurrences[$rawBibrole['occurrence_id']]
                    ->addPatron($person)
                    ->addCacheDependency('person_short.' . $person->getId());
            } elseif ($rawBibrole['type'] == 'scribe') {
                $occurrences[$rawBibrole['occurrence_id']]
                    ->addScribe($person)
                    ->addCacheDependency('person_short.' . $person->getId());
            }
            foreach ($persons[$rawBibrole['person_id']]->getCacheDependencies() as $cacheDependency) {
                $occurrences[$rawBibrole['occurrence_id']]
                    ->addCacheDependency($cacheDependency);
            }
        }

        $this->setDates($occurrences);

        // Genre
        $rawGenres = $this->dbs->getGenres($ids);
        foreach ($rawGenres as $rawGenre) {
            $occurrences[$rawGenre['occurrence_id']]
                ->setGenre(new Genre($rawGenre['genre_id'], $rawGenre['genre_name']))
                ->addCacheDependency('genre.' . $rawGenre['genre_id']);
        }

        $this->setComments($occurrences);

        // text and record status
        $rawStatuses = $this->dbs->getStatuses($ids);
        foreach ($rawStatuses as $rawStatus) {
            if ($rawStatus['type'] == 'occurrence_text') {
                $occurrences[$rawStatus['occurrence_id']]
                    ->setTextStatus(new Status($rawStatus['status_id'], $rawStatus['status_name']))
                    ->addCacheDependency('status.' . $rawStatus['status_id']);
            }
            if ($rawStatus['type'] == 'occurrence_record') {
                $occurrences[$rawStatus['occurrence_id']]
                    ->setRecordStatus(new Status($rawStatus['status_id'], $rawStatus['status_name']))
                    ->addCacheDependency('status.' . $rawStatus['status_id']);
            }
        }

        // Needed to index DBBE in elasticsearch
        $this->setBibliographies($occurrences);

        $this->setCache($occurrences, 'occurrence_short');

        return $cached + $occurrences;
    }

    public function getAllOccurrences(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'occurrence_id');
        return $this->getShortOccurrencesByIds($ids);
    }

    public function getOccurrenceById(int $id): Occurrence
    {
        $cache = $this->cache->getItem('occurrence.' . $id);
        if ($cache->isHit()) {
            return $cache->get();
        }

        // Get basic occurrence information
        $occurrences = $this->getShortOccurrencesByIds([$id]);
        if (count($occurrences) == 0) {
            throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
        }
        $occurrence = $occurrences[$id];

        $occurrenceArray = [$id => $occurrence];

        $this->setPrevIds($occurrenceArray);

        // type
        $rawTypes = $this->dbs->getTypes([$id]);
        if (count($rawTypes) == 1) {
            $type = $this->container->get('type_manager')->getMiniTypesByIds([$rawTypes[0]['type_id']])[$rawTypes[0]['type_id']];
            $occurrence
                ->setType($type)
                ->addCacheDependency('type.' . $rawTypes[0]['type_id']);
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

        // verses
        $rawVerses = $this->dbs->getVerses([$id]);
        if (count($rawContextualInfos) == 1) {
            $occurrence
                ->setVerses($rawVerses[0]['verses']);
        }

        // images
        $rawImages = $this->dbs->getImages([$id]);
        foreach ($rawImages as $rawImage) {
            if (strpos($rawImage['url'], 'http') === 0) {
                $occurrence
                    ->addImageLink(new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']));
            } else {
                $occurrence
                    ->addImage(new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']));
            }
        }

        $this->setCache([$occurrence->getId() => $occurrence], 'occurrence');

        return $occurrence;
    }

    public function getOccurrencesDependenciesByManuscript(int $manuscriptId): array
    {
        $rawIds = $this->dbs->getDepIdsByManuscriptId($manuscriptId);
        return $this->getMiniOccurrencesByIds(self::getUniqueIds($rawIds, 'occurrence_id'));
    }

    public function getOccurrencesDependenciesByPerson(int $personId): array
    {
        $rawIds = $this->dbs->getDepIdsByPersonId($personId);
        return $this->getMiniOccurrencesByIds(self::getUniqueIds($rawIds, 'occurrence_id'));
    }

    public function addOccurrence(stdClass $data): Occurrence
    {
        $this->dbs->beginTransaction();
        try {
            $occurrenceId = $this->dbs->insert();

            $newOccurrence = $this->updateOccurrence($occurrenceId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOccurrence;
    }

    public function updateOccurrence(int $occurrenceId, stdClass $data, bool $new = false): Occurrence
    {
        $this->dbs->beginTransaction();
        try {
            $occurrence = $this->getOccurrenceById($id);
            if ($occurrence == null) {
                throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
            }

            $newOccurrence = $this->getOccurrenceById($id);

            $this->updateModified($new ? null : $occurrence, $newOccurrence);

            // TODO: add actual update functions
            throw new Exception('Not implemented');


            // (re-)index in elastic search
            $this->ess->addOccurrence($newOccurrence);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOccurrence;
    }
}
