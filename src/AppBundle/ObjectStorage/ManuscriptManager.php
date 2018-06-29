<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use AppBundle\Model\Status;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Exceptions\NotFoundInDatabaseException;
use AppBundle\Model\Manuscript;
use AppBundle\Model\Origin;

class ManuscriptManager extends DocumentManager
{
    /**
     * Get manuscripts with enough information to get an id and a name
     * @param  array $ids
     * @return array
     */
    public function getMiniManuscriptsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'manuscript_mini');
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

        $this->setPublics($manuscripts);

        $this->setCache($manuscripts, 'manuscript_mini');

        return $cached + $manuscripts;
    }

    public function getShortManuscriptsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'manuscript_short');
        if (empty($ids)) {
            return $cached;
        }

        $manuscripts = $this->getMiniManuscriptsByIds($ids);

        // Remove all ids that did not match above
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

        $personIds = array_unique(array_merge($patronIds, $scribeIds, $occurrencePatronIds, $occurrenceScribeIds, $relatedPersonIds));
        $persons = [];
        if (count($personIds) > 0) {
            $persons = $this->container->get('person_manager')->getShortPersonsByIds($personIds);
        }

        $occurrences = [];
        if (count($occurrenceIds) > 0) {
            $occurrences = $this->container->get('occurrence_manager')->getMiniOccurrencesByIds($occurrenceIds);
        }

        foreach (array_merge($rawBibroles, $rawOccurrenceBibroles, $rawRelatedPersons) as $rawPerson) {
            $person = $persons[$rawPerson['person_id']];

            if (isset($rawPerson['type'])) {
                if (isset($rawPerson['occurrence_id'])) {
                    if ($rawPerson['type'] == 'patron') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addOccurrencePatron($person, $occurrences[$rawPerson['occurrence_id']])
                            ->addCacheDependency('person_short.' . $person->getId())
                            ->addCacheDependency('occurrence_mini.' . $rawPerson['occurrence_id']);
                    } elseif ($rawPerson['type'] == 'scribe') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addOccurrenceScribe($person, $occurrences[$rawPerson['occurrence_id']])
                            ->addCacheDependency('person_short.' . $person->getId())
                            ->addCacheDependency('occurrence_mini.' . $rawPerson['occurrence_id']);
                    }
                } else {
                    if ($rawPerson['type'] == 'patron') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addPatron($person)
                            ->addCacheDependency('person_short.' . $person->getId());
                    } elseif ($rawPerson['type'] == 'scribe') {
                        $manuscripts[$rawPerson['manuscript_id']]
                            ->addScribe($person)
                            ->addCacheDependency('person_short.' . $person->getId());
                    }
                }
            } else {
                $manuscripts[$rawPerson['manuscript_id']]
                    ->addRelatedPerson($person)
                    ->addCacheDependency('person_short.' . $person->getId());
            }
            foreach ($person->getCacheDependencies() as $cacheDependency) {
                $manuscripts[$rawPerson['manuscript_id']]
                    ->addCacheDependency($cacheDependency);
            }
        }

        $this->setDates($manuscripts);

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

        $this->setComments($manuscripts);

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
        $manuscripts = $this->getShortManuscriptsByIds([$id]);
        if (count($manuscripts) == 0) {
            throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
        }
        $manuscript = $manuscripts[$id];

        $manusrciptArray = [$id => $manuscript];

        $this->setBibliographies($manusrciptArray);

        // Diktyon
        $rawDiktyons = $this->dbs->getDiktyons([$id]);
        if (count($rawDiktyons) == 1) {
            $manuscript->setDiktyon($rawDiktyons[0]['diktyon_id']);
        }

        // Occurrences
        $rawOccurrences = $this->dbs->getOccurrences([$id]);
        if (count($rawOccurrences) > 0) {
            $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
            $occurrences = $this->container->get('occurrence_manager')->getMiniOccurrencesByIds($occurrenceIds);
            foreach ($rawOccurrences as $rawOccurrence) {
                $manuscript
                    ->addOccurrence($occurrences[$rawOccurrence['occurrence_id']])
                    ->addCacheDependency('occurrence_mini.' . $rawOccurrence['occurrence_id']);
            }
        }

        // status
        $rawStatuses = $this->dbs->getStatuses([$id]);
        if (count($rawStatuses) == 1) {
            $manuscript
                ->setStatus(new Status($rawStatuses[0]['status_id'], $rawStatuses[0]['status_name']))
                ->addCacheDependency('status.' . $rawStatuses[0]['status_id']);
        }

        // Illustrated
        $rawIllustrateds = $this->dbs->getIllustrateds([$id]);
        if (count($rawIllustrateds) == 1) {
            $manuscript->setIllustrated($rawIllustrateds[0]['illustrated']);
        }

        $this->setCache([$manuscript->getId() => $manuscript], 'manuscript');

        return $manuscript;
    }

    public function getManuscriptsDependenciesByRegion(int $regionId, bool $short = false): array
    {
        $rawIds = $this->dbs->getDepIdsByRegionId($regionId);
        if ($short) {
            return $this->getShortManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
        }
        return $this->getMiniManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByInstitution(int $institutionId): array
    {
        $rawIds = $this->dbs->getDepIdsByInstitutionId($institutionId);
        return $this->getMiniManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByCollection(int $collectionId): array
    {
        $rawIds = $this->dbs->getDepIdsByCollectionId($collectionId);
        return $this->getMiniManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByContent(int $contentId): array
    {
        $rawIds = $this->dbs->getDepIdsByContentId($contentId);
        return $this->getMiniManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByStatus(int $statusId): array
    {
        $rawIds = $this->dbs->getDepIdsByStatusId($statusId);
        return $this->getMiniManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
    }

    public function getManuscriptsDependenciesByPerson(int $personId): array
    {
        $rawIds = $this->dbs->getDepIdsByPersonId($personId);
        return $this->getMiniManuscriptsByIds(self::getUniqueIds($rawIds, 'manuscript_id'));
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
            // Located at needs to be saved in order for getManuscriptById
            $this->container->get('located_at_manager')->addLocatedAt(
                $manuscriptId,
                $data->locatedAt
            );
            // prevent locatedAt from being updated unnecessarily
            unset($data->locatedAt);

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

            // update manuscript data
            $cacheReload = [
                'mini' => $new,
                'short' => $new,
                'extended' => $new,
            ];
            if (property_exists($data, 'locatedAt')) {
                $cacheReload['mini'] = true;
                $this->container->get('located_at_manager')->updateLocatedAt(
                    $manuscript->getLocatedAt()->getId(),
                    $data->locatedAt
                );
            }
            if (property_exists($data, 'public')) {
                $cacheReload['mini'] = true;
                $this->updatePublic($manuscript, $data->public);
            }
            if (property_exists($data, 'content')) {
                $cacheReload['short'] = true;
                $this->updateContent($manuscript, $data->content);
            }
            if (property_exists($data, 'patrons')) {
                $cacheReload['short'] = true;
                $this->updatePatrons($manuscript, $data->patrons);
            }
            if (property_exists($data, 'scribes')) {
                $cacheReload['short'] = true;
                $this->updateScribes($manuscript, $data->scribes);
            }
            if (property_exists($data, 'relatedPersons')) {
                $cacheReload['short'] = true;
                $this->updateRelatedPersons($manuscript, $data->relatedPersons);
            }
            if (property_exists($data, 'date')) {
                $cacheReload['short'] = true;
                $this->updateDate($manuscript, 'completed at', $manuscript->getDate(), $data->date);
            }
            if (property_exists($data, 'origin')) {
                $cacheReload['short'] = true;
                $this->updateOrigin($manuscript, $data->origin);
            }
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePublicComment($manuscript, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePrivateComment($manuscript, $data->privateComment);
            }
            if (property_exists($data, 'occurrenceOrder')) {
                $cacheReload['extended'] = true;
                $this->updateOccurrenceOrder($manuscript, $data->occurrenceOrder);
            }
            if (property_exists($data, 'bibliography')) {
                $cacheReload['extended'] = true;
                $this->updateBibliography($manuscript, $data->bibliography);
            }
            if (property_exists($data, 'diktyon')) {
                if (!is_numeric($data->diktyon)) {
                    throw new BadRequestHttpException('Incorrect diktyon data.');
                }
                $cacheReload['extended'] = true;
                $this->updateDiktyon($manuscript, $data->diktyon);
            }
            if (property_exists($data, 'status')) {
                $cacheReload['extended'] = true;
                $this->updateStatus($manuscript, $data);
            }
            if (property_exists($data, 'illustrated')) {
                if (!is_bool($data->diktyon)) {
                    throw new BadRequestHttpException('Incorrect illustrated data.');
                }
                $cacheReload['extended'] = true;
                $this->updateIllustrated($manuscript, $data->illustrated);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new manuscript data
            $this->resetCache($cacheReload, 'manuscript', $id);
            $newManuscript = $this->getManuscriptById($id);

            $this->updateModified($new ? null : $manuscript, $newManuscript);

            // (re-)index in elastic search
            $this->ess->addManuscript($newManuscript);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($newManuscript)) {
                $this->resetCache($cacheReload, 'manuscript', $id);
                $this->getManuscriptById($id);
            }
            throw $e;
        }

        return $newManuscript;
    }

    private function updateContent(Manuscript $manuscript, array $contents): void
    {
        foreach ($contents as $content) {
            if (!is_object($content)
                || !property_exists($content, 'id')
                || !is_numeric($content->id)
            ) {
                throw new BadRequestHttpException('Incorrect content data.');
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
        foreach ($patrons as $patron) {
            if (!is_object($patron) || !property_exists($patron, 'id') || !is_numeric($patron->id)) {
                throw new BadRequestHttpException('Incorrect patrons data.');
            }
        }

        $this->updateBibroles($manuscript, $patrons, $manuscript->getPatrons(), 'patron');
    }

    private function updateScribes(Manuscript $manuscript, array $scribes): void
    {
        foreach ($scribes as $scribe) {
            if (!is_object($scribe) || !property_exists($scribe, 'id') || !is_numeric($scribe->id)) {
                throw new BadRequestHttpException('Incorrect scribes data.');
            }
        }

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
        foreach ($persons as $person) {
            if (!is_object($person) || !property_exists($person, 'id') || !is_numeric($person->id)) {
                throw new BadRequestHttpException('Incorrect related persons data.');
            }
        }

        list($delIds, $addIds) = self::calcDiff($persons, $manuscript->getRelatedPersons());

        if (count($delIds) > 0) {
            $this->dbs->delRelatedPersons($manuscript->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addRelatedPerson($manuscript->getId(), $addId);
        }
    }

    private function updateOrigin(Manuscript $manuscript, stdClass $origin = null): void
    {
        if (empty($origin)) {
            if (!empty($manuscript->getOrigin())) {
                $this->dbs->deleteOrigin($manuscript->getId());
            }
        } elseif (!property_exists($origin, 'id') || !is_numeric($origin->id)) {
            throw new BadRequestHttpException('Incorrect origin data.');
        } else {
            if (empty($manuscript->getOrigin())) {
                $this->dbs->insertOrigin($manuscript->getId(), $origin->id);
            } else {
                $this->dbs->updateOrigin($manuscript->getId(), $origin->id);
            }
        }
    }

    public function updateOccurrenceOrder(Manuscript $manuscript, array $occurrenceOrder): void
    {
        foreach ($occurrenceOrder as $occurrence) {
            if (!is_object($occurrence) || !property_exists($occurrence, 'id') || !is_numeric($occurrence->id)) {
                throw new BadRequestHttpException('Incorrect occurrence order data.');
            }
        }

        $this->dbs->updateOccurrenceOrder(
            $manuscript->getId(),
            array_map(function ($occurrence, $order) {
                return [
                    'occurrence_id' => $occurrence->id,
                    'order' => $order,
                ];
            }, $occurrenceOrder, array_keys($occurrenceOrder))
        );
    }

    private function updateBibliography(Manuscript $manuscript, stdClass $bibliography): void
    {
        foreach (['book', 'article', 'bookChapter', 'onlineSource'] as $bibType) {
            $plurBibType = $bibType . 's';
            if (!property_exists($bibliography, $plurBibType) || !is_array($bibliography->$plurBibType)) {
                throw new BadRequestHttpException('Incorrect bibliography data.');
            }
            foreach ($bibliography->$plurBibType as $bib) {
                if (!is_object($bib)
                    || (property_exists($bib, 'id') && !is_numeric($bib->id))
                    || !property_exists($bib, $bibType) || !is_object($bib->$bibType)
                    || !property_exists($bib->$bibType, 'id') || !is_numeric($bib->$bibType->id)
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
                    if (!property_exists($bib, 'relUrl') || !is_string($bib->relUrl)
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                }
            }
        }

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
                    self::certainString($articleBib, 'rawPages')
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
                    self::certainString($bookChapterBib, 'rawPages')
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

    private function updateStatus(Manuscript $manuscript, stdClass $data): void
    {
        if ($data->status == null) {
            $this->dbs->deleteStatus($manuscript->getId());
        } elseif (!is_object($data->status)
            || !property_exists($data->status, 'id')
            || !is_numeric($data->status->id)
        ) {
            throw new BadRequestHttpException('Incorrect status data.');
        } else {
            $this->dbs->upsertStatus($manuscript->getId(), $data->status->id);
        }
    }

    private function updateIllustrated(Manuscript $manuscript, bool $illustrated): void
    {
        $this->dbs->updateIllustrated($manuscript->getId(), $illustrated);
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

    public function elasticIndex(array $miniManuscripts): void
    {
        $manuscriptIds = array_map(
            function ($miniManuscript) {
                return $miniManuscript->getId();
            },
            $miniManuscripts
        );
        $this->ess->addManuscripts($this->getShortManuscriptsByIds($manuscriptIds));
    }

    public function delManuscript(int $manuscriptId): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $manuscript = $this->getManuscriptById($manuscriptId);

            $this->dbs->delete($manuscriptId);

            $this->updateModified($manuscript, null);

            // empty cache
            $this->cache->invalidateTags([
                'manuscript_short.' . $manuscriptId,
                'manuscript.' . $manuscriptId,
                'manuscripts'
            ]);
            $this->cache->deleteItem('manuscript_short.' . $manuscriptId);
            $this->cache->deleteItem('manuscript.' . $manuscriptId);

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
