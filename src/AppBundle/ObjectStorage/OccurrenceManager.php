<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Genre;
use AppBundle\Model\Meter;
use AppBundle\Model\Occurrence;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        $rawIncipits = $this->dbs->getIncipit($ids);
        if (count($rawIncipits) > 0) {
            foreach ($rawIncipits as $rawIncipit) {
                $occurrences[$rawIncipit['occurrence_id']]
                    ->setIncipit($rawIncipit['incipit']);
            }
        }

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
            $manuscript = $manuscripts[$rawLocation['manuscript_id']];
            $occurrences[$rawLocation['occurrence_id']]
                ->setManuscript($manuscript);
            foreach ($manuscript->getCacheDependencies() as $cacheDependency) {
                $occurrences[$rawLocation['occurrence_id']]
                    ->addCacheDependency($cacheDependency);
            }
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
            $persons = $this->container->get('person_manager')->getPersonsByIds($personIds);
        }
        $keywordIds = self::getUniqueIds($rawSubjects, 'keyword_id');
        $keywords = [];
        if (count($keywordIds) > 0) {
            $keywords = $this->container->get('keyword_manager')->getKeywordsByIds($keywordIds);
        }
        foreach ($rawSubjects as $rawSubject) {
            if (isset($rawSubject['person_id'])) {
                foreach ($persons[$rawBibrole['person_id']]->getCacheDependencies() as $cacheDependency) {
                    $occurrences[$rawBibrole['occurrence_id']]
                        ->addCacheDependency($cacheDependency);
                }
                $occurrences[$rawSubject['occurrence_id']]
                    ->addSubject($persons[$rawSubject['person_id']])
                    ->addCacheDependency('person.' . $rawSubject['person_id']);
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
        $personIds = array_merge($patronIds, $scribeIds);
        $persons = [];
        if (count($personIds) > 0) {
            $persons = $this->container->get('person_manager')->getPersonsByIds($personIds);
        }
        foreach ($rawBibroles as $rawBibrole) {
            $person = $persons[$rawBibrole['person_id']];
            if ($rawBibrole['type'] == 'patron') {
                $occurrences[$rawBibrole['occurrence_id']]
                    ->addPatron($person)
                    ->addCacheDependency('person.' . $person->getId());
            } elseif ($rawBibrole['type'] == 'scribe') {
                $occurrences[$rawBibrole['occurrence_id']]
                    ->addScribe($person)
                    ->addCacheDependency('person.' . $person->getId());
            }
            foreach ($persons[$rawBibrole['person_id']]->getCacheDependencies() as $cacheDependency) {
                $occurrences[$rawBibrole['occurrence_id']]
                    ->addCacheDependency($cacheDependency);
            }
        }

        $this->setDates($occurrences, $ids);

        // Genre
        $rawGenres = $this->dbs->getGenres($ids);
        foreach ($rawGenres as $rawGenre) {
            $occurrences[$rawGenre['occurrence_id']]
                ->setGenre(new Genre($rawGenre['genre_id'], $rawGenre['genre_name']))
                ->addCacheDependency('genre.' . $rawGenre['genre_id']);
        }

        // Public state
        $rawPublics = $this->dbs->getPublics($ids);
        foreach ($rawPublics as $rawPublic) {
            $occurrences[$rawPublic['occurrence_id']]
                // default: true (if no value is set in the database)
                ->setPublic(isset($rawPublic['public']) ? $rawPublic['public'] : true);
        }

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
        $occurrences= $this->getShortOccurrencesByIds([$id]);
        if (count($occurrences) == 0) {
            throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
        }
        $occurrence = $occurrences[$id];

        $this->setBibliographies($occurrence, $id);

        $this->setCache([$occurrence->getId() => $occurrence], 'occurrence');

        return $occurrence;
    }

    public function getOccurrencesDependenciesByManuscript(int $manuscriptId): array
    {
        $rawIds = $this->dbs->getDepIdsByManuscriptId($manuscriptId);
        return $this->getMiniOccurrencesByIds(self::getUniqueIds($rawIds, 'occurrence_id'));
    }
}
