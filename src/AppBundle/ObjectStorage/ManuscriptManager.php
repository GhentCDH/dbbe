<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;
use AppBundle\Model\Status;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Manuscript;
use AppBundle\Model\Origin;

class ManuscriptManager extends DocumentManager
{
    /**
     * Get manuscripts with enough information to get an id and a name
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Manuscript::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $manuscripts = [];
                // LocatedAts
                // locatedAts are identifiedd by document ids
                $locatedAts = $this->container->get('located_at_manager')->get($ids);
                if (count($locatedAts) == 0) {
                    return [];
                }
                foreach ($locatedAts as $locatedAt) {
                     $manuscript = (new Manuscript())
                        ->setId($locatedAt->getId())
                        ->setLocatedAt($locatedAt);

                    $manuscripts[$manuscript->getId()] = $manuscript;
                }

                $this->setPublics($manuscripts);

                return $manuscripts;
            }
        );
    }

    /**
     * Get manuscripts with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->wrapLevelCache(
            Manuscript::CACHENAME,
            'short',
            $ids,
            function ($ids) {
                $manuscripts = $this->getMini($ids);

                // Remove all ids that did not match above
                $ids = array_keys($manuscripts);

                // Contents
                $rawContents = $this->dbs->getContents($ids);
                if (count($rawContents) > 0) {
                    $contentIds = self::getUniqueIds($rawContents, 'genre_id');
                    $contentsWithParents = $this->container->get('content_manager')->getWithParents($contentIds);
                    foreach ($rawContents as $rawContent) {
                        $contentWithParents = $contentsWithParents[$rawContent['genre_id']];
                        $manuscripts[$rawContent['manuscript_id']]
                            ->addContentWithParents($contentWithParents);
                    }
                }

                $this->setPersonRoles($manuscripts);

                // Roles via occurrences
                $rawOccurrenceRoles = $this->dbs->getOccurrencePersonRoles($ids);
                $personIds = self::getUniqueIds($rawOccurrenceRoles, 'person_id');
                $occurrenceIds = self::getUniqueIds($rawOccurrenceRoles, 'occurrence_id');

                $persons = [];
                if (count($personIds) > 0) {
                    $persons = $this->container->get('person_manager')->getShort($personIds);
                }
                $occurrences = [];
                if (count($occurrenceIds) > 0) {
                    $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
                }

                $roles = $this->container->get('role_manager')->getWithData($rawOccurrenceRoles);

                foreach ($rawOccurrenceRoles as $raw) {
                    $manuscripts[$raw['manuscript_id']]
                        ->addOccurrencePersonRole(
                            $roles[$raw['role_id']],
                            $persons[$raw['person_id']],
                            $occurrences[$raw['occurrence_id']]
                        );
                }

                $this->setDates($manuscripts);

                // Origin
                $rawOrigins = $this->dbs->getOrigins($ids);
                if (count($rawOrigins) > 0) {
                    $locationIds = self::getUniqueIds($rawOrigins, 'location_id');
                    $locations = $this->container->get('location_manager')->get($locationIds);

                    foreach ($rawOrigins as $rawOrigin) {
                        $manuscripts[$rawOrigin['manuscript_id']]
                            ->setOrigin(Origin::fromLocation($locations[$rawOrigin['location_id']]));
                    }
                }

                $this->setComments($manuscripts);

                return $manuscripts;
            }
        );
    }

    /**
     * Get a single manuscript with all information
     * @param  int        $id
     * @return Manuscript
     */
    public function getFull(int $id): Manuscript
    {
        return $this->wrapSingleLevelCache(
            Manuscript::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic manuscript information
                $manuscripts = $this->getShort([$id]);

                if (count($manuscripts) == 0) {
                    throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
                }

                $this->setIdentifications($manuscripts);

                $this->setBibliographies($manuscripts);

                $manuscript = $manuscripts[$id];

                // Occurrences
                $rawOccurrences = $this->dbs->getOccurrences([$id]);
                if (count($rawOccurrences) > 0) {
                    $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
                    $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
                    foreach ($rawOccurrences as $rawOccurrence) {
                        $manuscript->addOccurrence($occurrences[$rawOccurrence['occurrence_id']]);
                    }
                }

                // status
                $rawStatuses = $this->dbs->getStatuses([$id]);
                if (count($rawStatuses) == 1) {
                    $statuses = $this->container->get('status_manager')->getWithData($rawStatuses);
                    $manuscript->setStatus($statuses[$rawStatuses[0]['status_id']]);
                }

                // Illustrated
                $rawIllustrateds = $this->dbs->getIllustrateds([$id]);
                if (count($rawIllustrateds) == 1) {
                    $manuscript->setIllustrated($rawIllustrateds[0]['illustrated']);
                }

                return $manuscript;
            }
        );
    }

    public function getAllMini(string $sortFunction = null): array
    {
        return parent::getAllMini($sortFunction == null ? 'getDescription' : $sortFunction);
    }

    public function getRegionDependencies(int $regionId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionId($regionId), $short ? 'getShort' : 'getMini');
    }

    public function getRegionDependenciesWithChildren(int $regionId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionIdWithChildren($regionId), $short ? 'getShort' : 'getMini');
    }

    public function getInstitutionDependencies(int $institutionId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByInstitutionId($institutionId), $short ? 'getShort' : 'getMini');
    }

    public function getCollectionDependencies(int $collectionId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByCollectionId($collectionId), $short ? 'getShort' : 'getMini');
    }

    public function getContentDependencies(int $contentId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByContentId($contentId), $short ? 'getShort' : 'getMini');
    }

    public function getContentDependenciesWithChildren(int $contentId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByContentIdWithChildren($contentId), $short ? 'getShort' : 'getMini');
    }

    public function getStatusDependencies(int $statusId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByStatusId($statusId), $short ? 'getShort' : 'getMini');
    }

    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short ? 'getShort' : 'getMini');
    }

    public function getRoleDependencies(int $roleId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRoleId($roleId), $short ? 'getShort' : 'getMini');
    }

    public function getOccurrenceDependencies(int $occurrenceId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOccurrenceId($occurrenceId), $short ? 'getShort' : 'getMini');
    }

    public function getArticleDependencies(int $articleId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByArticleId($articleId), $short ? 'getShort' : 'getMini');
    }

    public function getBookDependencies(int $bookId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookId($bookId), $short ? 'getShort' : 'getMini');
    }

    public function getBookChapterDependencies(int $bookChapterId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookChapterId($bookChapterId), $short ? 'getShort' : 'getMini');
    }

    public function getOnlineSourceDependencies(int $onlineSourceId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOnlineSourceId($onlineSourceId), $short ? 'getShort' : 'getMini');
    }

    public function add(stdClass $data): Manuscript
    {
        $this->dbs->beginTransaction();
        try {
            // locatedAt is mandatory
            if (!property_exists($data, 'locatedAt')) {
                throw new BadRequestHttpException('Incorrect data.');
            }
            $id = $this->dbs->insert();
            // Located at needs to be saved in order for getFull
            $this->container->get('located_at_manager')->addLocatedAt(
                $id,
                $data->locatedAt
            );
            // prevent locatedAt from being updated unnecessarily
            unset($data->locatedAt);

            $newManuscript = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            throw $e;
        }

        return $newManuscript;
    }

    public function update(int $id, stdClass $data, bool $isNew = false): Manuscript
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
            }

            $cacheReload = [
                'mini' => $isNew,
                'short' => $isNew,
                'full' => $isNew,
            ];
            if (property_exists($data, 'locatedAt')) {
                $cacheReload['mini'] = true;
                $this->container->get('located_at_manager')->updateLocatedAt(
                    $old->getLocatedAt()->getId(),
                    $data->locatedAt
                );
            }
            if (property_exists($data, 'public')) {
                $cacheReload['mini'] = true;
                $this->updatePublic($old, $data->public);
            }
            if (property_exists($data, 'content')) {
                $cacheReload['short'] = true;
                $this->updateContent($old, $data->content);
            }
            $roles = $this->container->get('role_manager')->getRolesByType('manuscript');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $cacheReload['short'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'date')) {
                $cacheReload['short'] = true;
                $this->updateDate($old, 'completed at', $old->getDate(), $data->date);
            }
            if (property_exists($data, 'origin')) {
                $cacheReload['short'] = true;
                $this->updateOrigin($old, $data->origin);
            }
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePublicComment($old, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePrivateComment($old, $data->privateComment);
            }
            if (property_exists($data, 'occurrenceOrder')) {
                $cacheReload['full'] = true;
                $this->updateOccurrenceOrder($old, $data->occurrenceOrder);
            }
            $identifiers = $this->container->get('identifier_manager')->getIdentifiersByType('manuscript');
            foreach ($identifiers as $identifier) {
                if (property_exists($data, $identifier->getSystemName())) {
                    $cacheReload['full'] = true;
                    $this->updateIdentification($old, $identifier, $data->{$identifier->getSystemName()});
                }
            }
            if (property_exists($data, 'bibliography')) {
                if (!is_object($data->bibliography)) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                $cacheReload['full'] = true;
                $this->updateBibliography($old, $data->bibliography);
            }
            if (property_exists($data, 'status')) {
                if (!(is_object($data->status) || empty($data->status))) {
                    throw new BadRequestHttpException('Incorrect text status data.');
                }
                $cacheReload['full'] = true;
                $this->updateStatus($old, $data->status, Status::MANUSCRIPT);
            }
            if (property_exists($data, 'illustrated')) {
                if (!is_bool($data->illustrated)) {
                    throw new BadRequestHttpException('Incorrect illustrated data.');
                }
                $cacheReload['full'] = true;
                $this->updateIllustrated($old, $data->illustrated);
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

            // update Elastic occurrences
            if ($cacheReload['mini']) {
                $occurrences = $this->container->get('occurrence_manager')->getManuscriptDependencies($id, true);
                $this->container->get('occurrence_manager')->elasticIndex($occurrences);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset cache and elasticsearch on elasticsearch error
            if (isset($new)) {
                $this->reset([$id]);

                // New manuscripts cannot have occurrence dependencies
                // Elastic occurrences are only updated when mini information is modified
                if (!$isNew && $cacheReload['mini']) {
                    $occurrences = $this->container->get('occurrence_manager')->getManuscriptDependencies($id, true);
                    $this->container->get('occurrence_manager')->elasticIndex($occurrences);
                }
            }

            throw $e;
        }

        return $new;
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

    private function updateIllustrated(Manuscript $manuscript, bool $illustrated): void
    {
        $this->dbs->updateIllustrated($manuscript->getId(), $illustrated);
    }

    public function delete(int $manuscriptId): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $manuscript = $this->getFull($manuscriptId);

            $this->dbs->delete($manuscriptId);

            $this->updateModified($manuscript, null);

            // empty cache and remove from elasticsearch
            $this->reset([$id]);

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
}
