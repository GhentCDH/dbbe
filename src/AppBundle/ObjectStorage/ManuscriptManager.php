<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use AppBundle\Exceptions\NotFoundInDatabaseException;

use AppBundle\Model\Collection;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Institution;
use AppBundle\Model\Library;
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
                ->setLocation($location);
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
        // Bundle to reduce number of database requests
        $rawBibroles = $this->dbs->getBibroles($ids, ['patron', 'scribe']);
        $patronIds = self::getUniqueIds($rawBibroles, 'person_id', 'type', 'patron');
        $scribeIds = self::getUniqueIds($rawBibroles, 'person_id', 'type', 'scribe');
        $rawRelatedPersons = $this->dbs->getRelatedPersons($ids);
        $relatedPersonIds = self::getUniqueIds($rawRelatedPersons, 'person_id');
        $personIds = array_merge($patronIds, $scribeIds, $relatedPersonIds);
        $rawPersons = $rawBibroles + $rawRelatedPersons;
        if (count($personIds) > 0) {
            $persons = $this->oms['person_manager']->getPersonsByIds($personIds);

            foreach ($rawPersons as $rawPerson) {
                $person = $persons[$rawPerson['person_id']];
                if (in_array($rawPerson['person_id'], $patronIds)) {
                    $manuscripts[$rawPerson['manuscript_id']]
                        ->addPatron($person)
                        ->addCacheDependency('person.' . $person->getId());
                }
                if (in_array($rawPerson['person_id'], $scribeIds)) {
                    $manuscripts[$rawPerson['manuscript_id']]
                        ->addScribe($person)
                        ->addCacheDependency('person.' . $person->getId());
                }
                // only display related persons if not in patrons or scribes list
                if (in_array($rawPerson['person_id'], $relatedPersonIds)
                    && !in_array($rawPerson['person_id'], $patronIds)
                    && !in_array($rawPerson['person_id'], $scribeIds)
                ) {
                    $manuscripts[$rawPerson['manuscript_id']]
                        ->addRelatedPerson($person)
                        ->addCacheDependency('person.' . $person->getId());
                }
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

        $this->setCache([$manuscript], 'manuscript');

        return $manuscript;
    }

    public function updateManuscript(int $id, stdClass $data): ?Manuscript
    {
        $manuscript = $this->getManuscriptById($id);
        if ($manuscript == null) {
            return null;
        }

        // construct manuscript
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'library':
                    if (!property_exists($data, 'collection') || empty($data->collection)) {
                        $manuscript->getLocation()->setLibrary(new Library($value->id, $value->name));
                    }
                    break;
                case 'collection':
                    if (empty($value)) {
                        $manuscript->getLocation()->setCollection(null);
                    } else {
                        $manuscript->getLocation()->setCollection(new Collection($value->id, $value->name));
                    }
                    break;
                case 'shelf':
                    $manuscript->getLocation()->setShelf($value);
                    break;
            }
        }

        // save manuscript to database
        if (property_exists($data, 'shelf')) {
            $this->oms['location_manager']->updateShelf($manuscript->getLocation());
        }
        if (property_exists($data, 'library')  || property_exists($data, 'collection')) {
            // update location
            $this->oms['location_manager']->updateLocation($manuscript->getLocation());

            // set new location
            $locationId = $manuscript->getLocation()->getId();
            $locations = $this->oms['location_manager']->getLocationsByIds([$locationId]);
            if (count($locations) != 1) {
                throw NotFoundInDatabaseException('Location not found');
            }
            $manuscript->setLocation($locations[$locationId]);
        }

        $this->setCache([$manuscript], 'manuscript');

        return $manuscript;
    }
}
