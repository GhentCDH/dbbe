<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use AppBundle\Exceptions\NotFoundInDatabaseException;

use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Institution;
use AppBundle\Model\Manuscript;
use AppBundle\Model\Origin;
use AppBundle\Model\Region;

class ManuscriptManager extends ObjectManager
{
    public function getManuscriptsByIds(array $ids): array
    {
        list($cached_short, $ids) = $this->getCache($ids, 'manuscript_short');
        list($cached, $ids) = $this->getCache($ids, 'manuscript');
        if (empty($ids)) {
            return $cached_short + $cached;
        }

        $manuscripts = [];
        // Locations
        // locations are identifiedd by document ids
        $locations = $this->oms['location_manager']->getLocationsByIds($ids);
        if (count($locations) == 0) {
            return $cached_short + $cached;
        }
        foreach ($locations as $location) {
            $manuscripts[$location->getId()] = (new Manuscript())
                ->setId($location->getId())
                ->setLocation($location)
                ->addCacheDependency('location.' . $location->getId());
            foreach ($location->getCacheDependencies() as $cacheDependency) {
                $manuscripts[$location->getId()]
                    ->addCacheDependency($cacheDependency);
            }
        }

        $ids = array_keys($manuscripts);

        // Contents
        $rawContents = $this->dbs->getContents($ids);
        if (count($rawContents) > 0) {
            $contentIds = self::getUniqueIds($rawContents, 'genre_id');
            $contentsWithParents = $this->oms['content_manager']->getContentsWithParentsByIds($contentIds);
            foreach ($rawContents as $rawContent) {
                $contentWithParents = $contentsWithParents[$rawContent['genre_id']];
                $manuscripts[$rawContent['manuscript_id']]
                    ->addContentWithParents($contentWithParents);
                foreach ($contentWithParents->getCacheDependencies() as $cacheDependency) {
                    $manuscripts[$rawContent['manuscript_id']]
                        ->addCacheDependency($cacheDependency);
                }
            }
        }

        // Patrons, scribes, related persons
        // Both the direct patrons and scribes as the patrons and scribes of occurrences
        // Bundle to reduce number of database requests
        $rawBibroles = $this->dbs->getBibroles($ids, ['patron', 'scribe']);
        $patronIds = self::getUniqueIds($rawBibroles, 'person_id', 'type', 'patron');
        $scribeIds = self::getUniqueIds($rawBibroles, 'person_id', 'type', 'scribe');

        $rawOccurrenceBibroles = $this->dbs->getOccurrenceBibroles($ids, ['patron', 'scribe']);
        $occurrencePatronIds = self::getUniqueIds($rawOccurrenceBibroles, 'person_id', 'type', 'patron');
        $occurrenceScribeIds = self::getUniqueIds($rawOccurrenceBibroles, 'person_id', 'type', 'scribe');
        $occurrenceIds = self::getUniqueIds($rawOccurrenceBibroles, 'occurrence_id');

        $rawRelatedPersons = $this->dbs->getRelatedPersons($ids);
        $relatedPersonIds = self::getUniqueIds($rawRelatedPersons, 'person_id');

        $personIds = array_merge($patronIds, $scribeIds, $occurrencePatronIds, $occurrenceScribeIds, $relatedPersonIds);
        $persons = [];
        if (count($personIds) > 0) {
            $persons = $this->oms['person_manager']->getPersonsByIds($personIds);
        }

        $occurrences = [];
        if (count($occurrenceIds) > 0) {
            $occurrences = $this->oms['occurrence_manager']->getOccurrencesByIds($occurrenceIds);
        }

        foreach (($rawBibroles + $rawOccurrenceBibroles + $rawRelatedPersons) as $rawPerson) {
            $person = $persons[$rawPerson['person_id']];

            if (in_array($person->getId(), $patronIds)) {
                $manuscripts[$rawPerson['manuscript_id']]
                    ->addPatron($person)
                    ->addCacheDependency('person.' . $person->getId());
            }
            if (in_array($person->getId(), $scribeIds)) {
                $manuscripts[$rawPerson['manuscript_id']]
                    ->addScribe($person)
                    ->addCacheDependency('person.' . $person->getId());
            }
            if (in_array($person->getId(), $occurrencePatronIds)) {
                $manuscripts[$rawPerson['manuscript_id']]
                    ->addOccurrencePatron($person, $occurrences[$rawPerson['occurrence_id']])
                    ->addCacheDependency('person.' . $person->getId())
                    ->addCacheDependency('occurrence.' . $rawPerson['occurrence_id']);
            }
            if (in_array($person->getId(), $occurrenceScribeIds)) {
                $manuscripts[$rawPerson['manuscript_id']]
                    ->addOccurrenceScribe($person, $occurrences[$rawPerson['occurrence_id']])
                    ->addCacheDependency('person.' . $person->getId())
                    ->addCacheDependency('occurrence.' . $rawPerson['occurrence_id']);
            }
            // only display related persons if not in any patrons or scribes list
            if (in_array($person->getId(), $relatedPersonIds)
                && !in_array($person->getId(), $patronIds)
                && !in_array($person->getId(), $scribeIds)
                && !in_array($person->getId(), $occurrencePatronIds)
                && !in_array($person->getId(), $occurrenceScribeIds)
            ) {
                $manuscripts[$rawPerson['manuscript_id']]
                    ->addRelatedPerson($person)
                    ->addCacheDependency('person.' . $person->getId());
            }
        }

        // Date
        $rawCompletionDates = $this->dbs->getCompletionDates($ids);
        foreach ($rawCompletionDates as $rawCompletionDate) {
            $manuscripts[$rawCompletionDate['manuscript_id']]
                ->setDate(new FuzzyDate($rawCompletionDate['completion_date']));
        }

        // Origin
        $rawOrigins = $this->dbs->getOrigins($ids);
        if (count($rawOrigins) > 0) {
            $originIds = self::getUniqueIds($rawOrigins, 'region_id');
            $regionsWithParents = $this->oms['region_manager']->getRegionsWithParentsByIds($originIds);
            foreach ($rawOrigins as $rawOrigin) {
                $regionWithParents = $regionsWithParents[$rawOrigin['region_id']];
                $origin = (new Origin())
                    ->setId($rawOrigin['location_id'])
                    ->setRegionWithParents($regionWithParents);
                foreach ($regionWithParents->getCacheDependencies() as $cacheDependency) {
                    $manuscripts[$rawOrigin['manuscript_id']]
                        ->addCacheDependency($cacheDependency);
                }
                if (isset($rawOrigin['institution_id'])) {
                    $origin
                        ->setInstitution(
                            new Institution($rawOrigin['institution_id'], $rawOrigin['institution_name'])
                        );
                    $manuscripts[$rawOrigin['manuscript_id']]
                        ->addCacheDependency('institution.' . $rawOrigin['institution_id']);
                }
                $manuscripts[$rawOrigin['manuscript_id']]
                    ->setOrigin($origin);
            }
        }

        $this->setCache($manuscripts, 'manuscript_short');

        return $cached_short + $cached + $manuscripts;
    }

    public function getAllManuscripts(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'manuscript_id');
        return $this->getManuscriptsByIds($ids);
    }

    public function getManuscriptById($id): Manuscript
    {
        $cache = $this->cache->getItem('manuscript.' . $id);
        if ($cache->isHit()) {
            return $cache->get();
        }

        // Get basic manuscript information
        $manuscripts= $this->getManuscriptsByIds([$id]);
        if (count($manuscripts) == 0) {
            return null;
        }
        $manuscript = $manuscripts[$id];

        // Bibliography
        $rawBibliographies = $this->dbs->getBibliographies([$id]);
        $bookIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book');
        $articleIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'article');
        $bookChapterIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book_chapter');
        $onlineSourceIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'online_source');
        $bookBibliographies = $this->oms['bibliography_manager']->getBookBibliographiesByIds($bookIds);
        $articleBibliographies = $this->oms['bibliography_manager']->getArticleBibliographiesByIds($articleIds);
        $bookChapterBibliographies = $this->oms['bibliography_manager']->getBookChapterBibliographiesByIds($bookChapterIds);
        $onlineSourceBibliographies = $this->oms['bibliography_manager']->getOnlineSourceBibliographiesByIds($onlineSourceIds);
        $bibliographies =
            $bookBibliographies + $articleBibliographies + $bookChapterBibliographies + $onlineSourceBibliographies;
        foreach ($bibliographies as $bibliography) {
            foreach ($bibliography->getCacheDependencies() as $cacheDependency) {
                $manuscript->addCacheDePendency($cacheDependency);
            }
        }
        if (!empty($bibliographies)) {
            $manuscript->setBibliographies($bibliographies);
        }

        // Diktyon
        $rawDiktyons = $this->dbs->getDiktyons([$id]);
        if (count($rawDiktyons) == 1) {
            $manuscript->setDiktyon($rawDiktyons[0]['diktyon_id']);
        }

        // Comments
        $rawComments = $this->dbs->getComments([$id]);
        if (count($rawComments) == 1) {
            $manuscript->setPublicComment($rawComments[0]['public_comment']);
            $manuscript->setPrivateComment($rawComments[0]['private_comment']);
        }

        // Occurrences
        $rawOccurrences = $this->dbs->getOccurrences([$id]);
        if (count($rawOccurrences) > 0) {
            $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
            $occurrences = $this->oms['occurrence_manager']->getOccurrencesByIds($occurrenceIds);
            foreach ($occurrences as $occurrence) {
                $manuscript->addCacheDePendency('occurrence.' . $occurrence->getId());
            }
            $manuscript->setOccurrences($occurrences);
        }

        // Illustrated
        $rawIllustrateds = $this->dbs->getIllustrateds([$id]);
        if (count($rawComments) == 1) {
            $manuscript->setIllustrated($rawIllustrateds[0]['illustrated']);
        }

        $this->setCache([$manuscript->getId() => $manuscript], 'manuscript');

        return $manuscript;
    }

    public function updateManuscript(int $id, stdClass $data): ?Manuscript
    {
        $manuscript = $this->getManuscriptById($id);
        if ($manuscript == null) {
            return null;
        }

        // update manuscript data
        if (property_exists($data, 'library')
            && !(property_exists($data, 'collection') && !empty($data->collection))
        ) {
            $this->oms['location_manager']->updateLibrary($manuscript, $data->library);
        }
        if (property_exists($data, 'collection') && !empty($data->collection)) {
            $this->oms['location_manager']->updateCollection($manuscript, $data->collection);
        }
        if (property_exists($data, 'shelf')) {
            $this->oms['location_manager']->updateShelf($manuscript, $data->shelf);
        }
        if (property_exists($data, 'patrons')) {
            $this->updatePatrons($manuscript, $data->patrons);
        }

        // load new manuscript data
        $this->cache->deleteItem('manuscript_short.' . $id);
        $this->cache->deleteItem('manuscript.' . $id);
        $manuscript = $this->getManuscriptById($id);

        // re-index in elastic search
        $this->ess->addManuscript($manuscript);

        // update cache
        $this->setCache([$manuscript->getId() => $manuscript], 'manuscript');

        return $manuscript;
    }

    private function updatePatrons(Manuscript $manuscript, array $patrons)
    {
        $newPatronIds = array_map(
            function ($patron) {
                return $patron->id;
            },
            $patrons
        );
        $oldPatronIds = array_map(
            function ($patron) {
                return $patron->getId();
            },
            $manuscript->getPatrons()
        );

        $delPatronIds = array_diff($oldPatronIds, $newPatronIds);
        $addPatronIds = array_diff($newPatronIds, $oldPatronIds);

        if (count($delPatronIds) > 0) {
            $this->dbs->delBibroles($manuscript->getId(), 'patron', $delPatronIds);
        }
        foreach ($addPatronIds as $addPatronId) {
            $this->dbs->addBibrole($manuscript->getId(), 'patron', $addPatronId);
        }
    }
}
