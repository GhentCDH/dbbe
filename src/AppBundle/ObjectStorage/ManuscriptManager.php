<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Exceptions\NotFoundInDatabaseException;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Manuscript;
use AppBundle\Model\Origin;

class ManuscriptManager extends ObjectManager
{
    public function getShortManuscriptsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'manuscript_short');
        if (empty($ids)) {
            return $cached;
        }

        $manuscripts = [];
        // LocatedAts
        // locatedAts are identifiedd by document ids
        $locatedAts = $this->container->get('located_at_manager')->getLocatedAtsByIds($ids);
        if (count($locatedAts) == 0) {
            return $cached;
        }
        foreach ($locatedAts as $locatedAt) {
             $manuscript = (new Manuscript())
                ->setId($locatedAt->getId())
                ->setLocatedAt($locatedAt);

            $manuscripts[$manuscript->getId()] = $manuscript;
        }

        $ids = array_keys($manuscripts);

        // Contents
        $rawContents = $this->dbs->getContents($ids);
        if (count($rawContents) > 0) {
            $contentIds = self::getUniqueIds($rawContents, 'genre_id');
            $contentsWithParents = $this->container->get('content_manager')->getContentsWithParentsByIds($contentIds);
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
            $persons = $this->container->get('person_manager')->getPersonsByIds($personIds);
        }

        $occurrences = [];
        if (count($occurrenceIds) > 0) {
            $occurrences = $this->container->get('occurrence_manager')->getOccurrencesByIds($occurrenceIds);
        }

        foreach (array_merge($rawBibroles, $rawOccurrenceBibroles, $rawRelatedPersons) as $rawPerson) {
            $person = $persons[$rawPerson['person_id']];

            if (isset($rawPerson['type'])) {
                if (isset($rawPerson['occurrence_id'])) {
                    if ($rawPerson['type'] == 'patron') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addOccurrencePatron($person, $occurrences[$rawPerson['occurrence_id']])
                            ->addCacheDependency('person.' . $person->getId())
                            ->addCacheDependency('occurrence.' . $rawPerson['occurrence_id']);
                    } elseif ($rawPerson['type'] == 'scribe') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addOccurrenceScribe($person, $occurrences[$rawPerson['occurrence_id']])
                            ->addCacheDependency('person.' . $person->getId())
                            ->addCacheDependency('occurrence.' . $rawPerson['occurrence_id']);
                    }
                } else {
                    if ($rawPerson['type'] == 'patron') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addPatron($person)
                            ->addCacheDependency('person.' . $person->getId());
                    } elseif ($rawPerson['type'] == 'scribe') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addScribe($person)
                            ->addCacheDependency('person.' . $person->getId());
                    }
                }
            } else {
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
            $locationIds = self::getUniqueIds($rawOrigins, 'location_id');
            $locations = $this->container->get('location_manager')->getLocationsByIds($locationIds);

            foreach ($rawOrigins as $rawOrigin) {
                $manuscripts[$rawOrigin['manuscript_id']]
                    ->setOrigin(Origin::fromLocation($locations[$rawOrigin['location_id']]));

                foreach ($manuscripts[$rawOrigin['manuscript_id']]->getOrigin()->getCacheDependencies() as $cacheDependency) {
                    $manuscripts[$rawOrigin['manuscript_id']]->addCacheDependency($cacheDependency);
                }
            }
        }

        // Public state
        $rawPublics = $this->dbs->getPublics($ids);
        foreach ($rawPublics as $rawPublic) {
            $manuscripts[$rawPublic['manuscript_id']]
                // default: true (if no value is set in the database)
                ->setPublic(isset($rawPublic['public']) ? $rawPublic['public'] : true);
        }

        $this->setCache($manuscripts, 'manuscript_short');

        return $cached + $manuscripts;
    }

    public function getAllManuscripts(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'manuscript_id');
        return $this->getShortManuscriptsByIds($ids);
    }

    public function getManuscriptById(int $id): Manuscript
    {
        $cache = $this->cache->getItem('manuscript.' . $id);
        if ($cache->isHit()) {
            return $cache->get();
        }

        // Get basic manuscript information
        $manuscripts= $this->getShortManuscriptsByIds([$id]);
        if (count($manuscripts) == 0) {
            throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
        }
        $manuscript = $manuscripts[$id];

        // Bibliography
        $rawBibliographies = $this->dbs->getBibliographies([$id]);
        $bookIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book');
        $articleIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'article');
        $bookChapterIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book_chapter');
        $onlineSourceIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'online_source');
        $bookBibliographies = $this->container->get('bibliography_manager')->getBookBibliographiesByIds($bookIds);
        $articleBibliographies = $this->container->get('bibliography_manager')->getArticleBibliographiesByIds($articleIds);
        $bookChapterBibliographies = $this->container->get('bibliography_manager')->getBookChapterBibliographiesByIds($bookChapterIds);
        $onlineSourceBibliographies = $this->container->get('bibliography_manager')->getOnlineSourceBibliographiesByIds($onlineSourceIds);
        $bibliographies =
            $bookBibliographies + $articleBibliographies + $bookChapterBibliographies + $onlineSourceBibliographies;
        foreach ($bibliographies as $bibliography) {
            foreach ($bibliography->getCacheDependencies() as $cacheDependency) {
                $manuscript->addCacheDependency($cacheDependency);
            }
            $manuscript->addCacheDependency('book_bibliography.' . $bibliography->getId());
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
            $occurrences = $this->container->get('occurrence_manager')->getOccurrencesByIds($occurrenceIds);
            foreach ($occurrences as $occurrence) {
                $manuscript->addCacheDependency('occurrence.' . $occurrence->getId());
            }
            $manuscript->setOccurrences($occurrences);
        }

        // Illustrated
        $rawIllustrateds = $this->dbs->getIllustrateds([$id]);
        if (count($rawIllustrateds) == 1) {
            $manuscript->setIllustrated($rawIllustrateds[0]['illustrated']);
        }

        $this->setCache([$manuscript->getId() => $manuscript], 'manuscript');

        return $manuscript;
    }

    public function getManuscriptsDependenciesByRegion(int $regionId): array
    {
        $rawIds = $this->dbs->getDepIdsByRegionId($regionId);
        return $this->getShortManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByInstitution(int $institutionId): array
    {
        $rawIds = $this->dbs->getDepIdsByInstitutionId($institutionId);
        return $this->getShortManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByCollection(int $collectionId): array
    {
        $rawIds = $this->dbs->getDepIdsByCollectionId($collectionId);
        return $this->getShortManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByContent(int $contentId): array
    {
        $rawIds = $this->dbs->getDepIdsByContentId($contentId);
        return $this->getShortManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function addManuscript(stdClass $data): Manuscript
    {
        $this->dbs->beginTransaction();
        try {
            // locatedAt is mandatory
            if (!property_exists($data, 'locatedAt')) {
                throw new BadRequestHttpException('Incorrect data.');
            }
            $manuscriptId = $this->dbs->insert();
            $this->container->get('located_at_manager')->addLocatedAt(
                $manuscriptId,
                $data->locatedAt
            );

            unset($data->locatedAt);
            if (!property_exists($data, 'public')) {
                $data->public = false;
            }

            $newManuscript = $this->updateManuscript($manuscriptId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newManuscript;
    }

    public function updateManuscript(int $id, stdClass $data, bool $new = false): Manuscript
    {
        $this->dbs->beginTransaction();
        try {
            $manuscript = $this->getManuscriptById($id);
            if ($manuscript == null) {
                throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
            }

            // TODO: sanitize data
            // update manuscript data
            $correct = $new;
            if (property_exists($data, 'locatedAt')) {
                $correct = true;
                $this->container->get('located_at_manager')->updateLocatedAt(
                    $manuscript->getLocatedAt()->getId(),
                    $data->locatedAt
                );
            }
            if (property_exists($data, 'content')) {
                $correct = true;
                $this->updateContent($manuscript, $data->content);
            }
            if (property_exists($data, 'patrons')) {
                $correct = true;
                $this->updatePatrons($manuscript, $data->patrons);
            }
            if (property_exists($data, 'scribes')) {
                $correct = true;
                $this->updateScribes($manuscript, $data->scribes);
            }
            if (property_exists($data, 'relatedPersons')) {
                $correct = true;
                $this->updateRelatedPersons($manuscript, $data->relatedPersons);
            }
            if (property_exists($data, 'date')) {
                $correct = true;
                $this->updateDate($manuscript, $data->date);
            }
            if (property_exists($data, 'origin')) {
                $correct = true;
                $this->updateOrigin($manuscript, $data->origin);
            }
            if (property_exists($data, 'bibliography')) {
                $correct = true;
                $this->updateBibliography($manuscript, $data->bibliography);
            }
            if (property_exists($data, 'diktyon')) {
                $correct = true;
                $this->updateDiktyon($manuscript, $data->diktyon);
            }
            if (property_exists($data, 'publicComment')) {
                $correct = true;
                $this->updatePublicComment($manuscript, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                $correct = true;
                $this->updatePrivateComment($manuscript, $data->privateComment);
            }
            if (property_exists($data, 'illustrated')) {
                $correct = true;
                $this->updateIllustrated($manuscript, $data->illustrated);
            }
            if (property_exists($data, 'public')) {
                $correct = true;
                $this->updatePublic($manuscript, $data->public);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new manuscript data
            $this->cache->deleteItem('manuscript_short.' . $id);
            $this->cache->deleteItem('manuscript.' . $id);
            $newManuscript = $this->getManuscriptById($id);

            $this->updateModified($new ? null : $manuscript, $newManuscript);

            // re-index in elastic search
            $this->ess->addManuscript($newManuscript);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newManuscript;
    }

    private function updateContent(Manuscript $manuscript, array $contents): void
    {
        if (!is_array($contents)) {
            throw new BadRequestHttpException('Incorrect data.');
        }
        foreach ($contents as $content) {
            if (!property_exists($content, 'id')
                && !is_numeric($content->id)
            ) {
                throw new BadRequestHttpException('Incorrect data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($contents, $manuscript->getContentsWithParents());

        if (count($delIds) > 0) {
            $this->dbs->delContents($manuscript->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addContent($manuscript->getId(), $addId);
        }
    }

    private function updatePatrons(Manuscript $manuscript, array $patrons): void
    {
        $this->updateBibroles($manuscript, $patrons, $manuscript->getPatrons(), 'patron');
    }

    private function updateScribes(Manuscript $manuscript, array $scribes): void
    {
        $this->updateBibroles($manuscript, $scribes, $manuscript->getScribes(), 'scribe');
    }

    private function updateBibroles(Manuscript $manuscript, array $newPersons, array $oldPersons, string $role): void
    {
        list($delIds, $addIds) = self::calcDiff($newPersons, $oldPersons);

        if (count($delIds) > 0) {
            $this->dbs->delBibroles($manuscript->getId(), $role, $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addBibrole($manuscript->getId(), $role, $addId);
        }
    }

    private function updateRelatedPersons(Manuscript $manuscript, array $persons): void
    {
        list($delIds, $addIds) = self::calcDiff($persons, $manuscript->getRelatedPersons());

        if (count($delIds) > 0) {
            $this->dbs->delRelatedPersons($manuscript->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addRelatedPerson($manuscript->getId(), $addId);
        }
    }

    private function updateDate(Manuscript $manuscript, stdClass $date): void
    {
        // TODO: allow deletion
        $dbDate = '('
            . (empty($date->floor) ? '-infinity' : $date->floor)
            . ', '
            . (empty($date->ceiling) ? 'infinity' : $date->ceiling)
            . ')';
        if (empty($manuscript->getDate())) {
            $this->dbs->insertCompletionDate($manuscript->getId(), $dbDate);
        } else {
            $this->dbs->updateCompletionDate($manuscript->getId(), $dbDate);
        }
    }

    private function updateOrigin(Manuscript $manuscript, stdClass $origin): void
    {
        $this->dbs->updateOrigin($manuscript->getId(), $origin->id);
    }

    private function updateBibliography(Manuscript $manuscript, stdClass $bibliography): void
    {
        $updateIds = [];
        $origBibIds = array_keys($manuscript->getBibliographies());
        // Add and update
        foreach ($bibliography->books as $bookBib) {
            if (!property_exists($bookBib, 'id')) {
                $this->container->get('bibliography_manager')->addBookBibliography(
                    $manuscript->getId(),
                    $bookBib->book->id,
                    self::certainString($bookBib, 'startPage'),
                    self::certainString($bookBib, 'endPage')
                );
            } elseif (in_array($bookBib->id, $origBibIds)) {
                $updateIds[] = $bookBib->id;
                $this->container->get('bibliography_manager')->updateBookBibliography(
                    $bookBib->id,
                    $bookBib->book->id,
                    self::certainString($bookBib, 'startPage'),
                    self::certainString($bookBib, 'endPage'),
                    self::certainString($bookBib, 'rawPages')
                );
            } else {
                throw new NotFoundInDatabaseException(
                    'Bibliography with id "' . $bookBib->id . '" not found '
                    . ' in manuscript with id "' . $manuscript->getId() . '".'
                );
            }
        }
        foreach ($bibliography->articles as $articleBib) {
            if (!property_exists($articleBib, 'id')) {
                $this->container->get('bibliography_manager')->addArticleBibliography(
                    $manuscript->getId(),
                    $articleBib->article->id,
                    self::certainString($articleBib, 'startPage'),
                    self::certainString($articleBib, 'endPage')
                );
            } elseif (in_array($articleBib->id, $origBibIds)) {
                $updateIds[] = $articleBib->id;
                $this->container->get('bibliography_manager')->updateArticleBibliography(
                    $articleBib->id,
                    $articleBib->article->id,
                    self::certainString($articleBib, 'startPage'),
                    self::certainString($articleBib, 'endPage'),
                    self::certainString($bookBib, 'rawPages')
                );
            } else {
                throw new NotFoundInDatabaseException(
                    'Bibliography with id "' . $articleBib->id . '" not found '
                    . ' in manuscript with id "' . $manuscript->getId() . '".'
                );
            }
        }
        foreach ($bibliography->bookChapters as $bookChapterBib) {
            if (!property_exists($bookChapterBib, 'id')) {
                $this->container->get('bibliography_manager')->addBookChapterBibliography(
                    $manuscript->getId(),
                    $bookChapterBib->bookChapter->id,
                    self::certainString($bookChapterBib, 'startPage'),
                    self::certainString($bookChapterBib, 'endPage')
                );
            } elseif (in_array($bookChapterBib->id, $origBibIds)) {
                $updateIds[] = $bookChapterBib->id;
                $this->container->get('bibliography_manager')->updateBookChapterBibliography(
                    $bookChapterBib->id,
                    $bookChapterBib->bookChapter->id,
                    self::certainString($bookChapterBib, 'startPage'),
                    self::certainString($bookChapterBib, 'endPage'),
                    self::certainString($bookBib, 'rawPages')
                );
            } else {
                throw new NotFoundInDatabaseException(
                    'Bibliography with id "' . $bookChapterBib->id . '" not found '
                    . ' in manuscript with id "' . $manuscript->getId() . '".'
                );
            }
        }
        foreach ($bibliography->onlineSources as $onlineSourceBib) {
            if (!property_exists($onlineSourceBib, 'id')) {
                $this->container->get('bibliography_manager')->addOnlineSourceBibliography(
                    $manuscript->getId(),
                    $onlineSourceBib->onlineSource->id,
                    self::certainString($onlineSourceBib, 'relUrl')
                );
            } elseif (in_array($onlineSourceBib->id, $origBibIds)) {
                $updateIds[] = $onlineSourceBib->id;
                $this->container->get('bibliography_manager')->updateOnlineSourceBibliography(
                    $onlineSourceBib->id,
                    $onlineSourceBib->onlineSource->id,
                    self::certainString($onlineSourceBib, 'relUrl')
                );
            } else {
                throw new NotFoundInDatabaseException(
                    'Bibliography with id "' . $onlineSourceBib->id . '" not found '
                    . ' in manuscript with id "' . $manuscript->getId() . '".'
                );
            }
        }
        // delete
        $delIds = [];
        foreach ($origBibIds as $origId) {
            if (!in_array($origId, $updateIds)) {
                $delIds[] = $origId;
            }
        }
        if (count($delIds) > 0) {
            $this->container->get('bibliography_manager')->delBibliographies(
                array_filter(
                    $manuscript->getBibliographies(),
                    function ($bibliography) use ($delIds) {
                        return in_array($bibliography->getId(), $delIds);
                    }
                )
            );
        }
    }

    private function updateDiktyon(Manuscript $manuscript, int $diktyon = null): void
    {
        if (empty($diktyon)) {
            if (!empty($manuscript->getDiktyon())) {
                $this->dbs->deleteDiktyon($manuscript->getId());
            }
        } else {
            $this->dbs->upsertDiktyon($manuscript->getId(), $diktyon);
        }
    }

    private function updatePublicComment(Manuscript $manuscript, string $publicComment = null): void
    {
        if (empty($publicComment)) {
            if (!empty($manuscript->getPublicComment())) {
                $this->dbs->updatePublicComment($manuscript->getId(), '');
            }
        } else {
            $this->dbs->updatePublicComment($manuscript->getId(), $publicComment);
        }
    }

    private function updateIllustrated(Manuscript $manuscript, bool $illustrated): void
    {
        $this->dbs->updateIllustrated($manuscript->getId(), $illustrated);
    }

    private function updatePublic(Manuscript $manuscript, bool $public): void
    {
        $this->dbs->updatePublic($manuscript->getId(), $public);
    }

    private function updatePrivateComment(Manuscript $manuscript, string $privateComment = null): void
    {
        if (empty($privateComment)) {
            if (!empty($manuscript->getPrivateComment())) {
                $this->dbs->updatePrivateComment($manuscript->getId(), '');
            }
        } else {
            $this->dbs->updatePrivateComment($manuscript->getId(), $privateComment);
        }
    }

    public function elasticIndex(array $manuscripts): void
    {
        $this->ess->addManuscripts($manuscripts);
    }

    public function delManuscript(int $manuscriptId): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $manuscript = $this->getManuscriptById($manuscriptId);

            $this->dbs->delete($manuscriptId);

            // empty cache
            $this->cache->invalidateTags([
                'manuscript_short.' . $manuscriptId,
                'manuscript.' . $manuscriptId,
                'manuscripts'
            ]);
            $this->cache->deleteItem('manuscript_short.' . $manuscriptId);
            $this->cache->deleteItem('manuscript.' . $manuscriptId);

            $this->updateModified($manuscript, null);

            // delete from elastic search
            $this->ess->delManuscript($manuscript);

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

    private static function calcDiff(array $newJsonArray, array $oldObjectArray): array
    {
        $newIds = array_map(
            function ($newJsonItem) {
                return $newJsonItem->id;
            },
            $newJsonArray
        );
        $oldIds = array_map(
            function ($oldObjectItem) {
                return $oldObjectItem->getId();
            },
            $oldObjectArray
        );

        $delIds = array_diff($oldIds, $newIds);
        $addIds = array_diff($newIds, $oldIds);

        return [$delIds, $addIds];
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
