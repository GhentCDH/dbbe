<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Psr\Cache\CacheItemPoolInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Manuscript;
use AppBundle\Model\Origin;
use AppBundle\Model\Role;
use AppBundle\Model\Status;
use AppBundle\Service\DatabaseService\DatabaseServiceInterface;
use AppBundle\Service\ElasticSearchService\ElasticSearchServiceInterface;

class ManuscriptManager extends DocumentManager
{
    public function __construct(
        DatabaseServiceInterface $databaseService,
        CacheItemPoolInterface $cacheItemPool,
        ContainerInterface $container,
        ElasticSearchServiceInterface $elasticSearchService = null,
        TokenStorageInterface $tokenStorage = null
    ) {
        parent::__construct($databaseService, $cacheItemPool, $container, $elasticSearchService, $tokenStorage);
        $this->en = 'manuscript';
    }

    /**
     * Get manuscripts with enough information to get an id and a name
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
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

    /**
     * Get manuscripts with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'manuscript_short');
        if (empty($ids)) {
            return $cached;
        }

        $manuscripts = $this->getMini($ids);

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

        foreach ($rawOccurrenceRoles as $raw) {
            $manuscripts[$raw['manuscript_id']]
                ->addOccurrencePersonRole(
                    new Role($raw['role_id'], json_decode($raw['role_usage']), $raw['role_system_name'], $raw['role_name']),
                    $persons[$raw['person_id']],
                    $occurrences[$raw['occurrence_id']]
                )
                ->addCacheDependency('role.' . $raw['role_id'])
                ->addCacheDependency('person_short.' . $raw['person_id'])
                ->addCacheDependency('occurrence_mini.' . $raw['occurrence_id']);
            foreach ($persons[$raw['person_id']]->getCacheDependencies() as $cacheDependency) {
                $manuscripts[$raw['manuscript_id']]
                    ->addCacheDependency($cacheDependency);
            }
            foreach ($occurrences[$raw['occurrence_id']]->getCacheDependencies() as $cacheDependency) {
                $manuscripts[$raw['manuscript_id']]
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

    /**
     * Get a single manuscript with all information
     * @param  int        $id
     * @return Manuscript
     */
    public function getFull(int $id): Manuscript
    {
        $cache = $this->cache->getItem('manuscript_full.' . $id);
        if ($cache->isHit()) {
            return $cache->get();
        }

        // Get basic manuscript information
        $manuscripts = $this->getShort([$id]);
        if (count($manuscripts) == 0) {
            throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
        }
        $manuscript = $manuscripts[$id];

        $manusrciptArray = [$id => $manuscript];

        $this->setIdentifications($manusrciptArray);

        $this->setBibliographies($manusrciptArray);

        // Occurrences
        $rawOccurrences = $this->dbs->getOccurrences([$id]);
        if (count($rawOccurrences) > 0) {
            $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
            $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
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

        $this->setCache([$manuscript->getId() => $manuscript], 'manuscript_full');

        return $manuscript;
    }

    public function getRegionDependencies(int $regionId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionId($regionId), $short);
    }

    public function getInstitutionDependencies(int $institutionId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByInstitutionId($institutionId));
    }

    public function getCollectionDependencies(int $collectionId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByCollectionId($collectionId));
    }

    public function getContentDependencies(int $contentId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByContentId($contentId));
    }

    public function getStatusDependencies(int $statusId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByStatusId($statusId));
    }

    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short);
    }

    public function getRoleDependencies(int $roleId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRoleId($roleId));
    }

    public function getOccurrenceDependencies(int $occurrenceId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOccurrenceId($occurrenceId));
    }

    public function add(stdClass $data): Manuscript
    {
        $this->dbs->beginTransaction();
        try {
            // locatedAt is mandatory
            if (!property_exists($data, 'locatedAt')) {
                throw new BadRequestHttpException('Incorrect data.');
            }
            $manuscriptId = $this->dbs->insert();
            // Located at needs to be saved in order for getFull
            $this->container->get('located_at_manager')->addLocatedAt(
                $manuscriptId,
                $data->locatedAt
            );
            // prevent locatedAt from being updated unnecessarily
            unset($data->locatedAt);

            $newManuscript = $this->update($manuscriptId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newManuscript;
    }

    public function update(int $id, stdClass $data, bool $new = false): Manuscript
    {
        $this->dbs->beginTransaction();
        try {
            $manuscript = $this->getFull($id);
            if ($manuscript == null) {
                throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
            }

            // update manuscript data
            $cacheReload = [
                'mini' => $new,
                'short' => $new,
                'full' => $new,
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
            $roles = $this->container->get('role_manager')->getRolesByType('manuscript');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $cacheReload['short'] = true;
                    $this->updatePersonRole($manuscript, $role, $data->{$role->getSystemName()});
                }
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
                $cacheReload['full'] = true;
                $this->updateOccurrenceOrder($manuscript, $data->occurrenceOrder);
            }
            $identifiers = $this->container->get('identifier_manager')->getIdentifiersByType('manuscript');
            foreach ($identifiers as $identifier) {
                if (property_exists($data, $identifier->getSystemName())) {
                    $cacheReload['full'] = true;
                    $this->updateIdentification($manuscript, $identifier, $data->{$identifier->getSystemName()});
                }
            }
            if (property_exists($data, 'bibliography')) {
                $cacheReload['full'] = true;
                $this->updateBibliography($manuscript, $data->bibliography);
            }
            if (property_exists($data, 'status')) {
                $cacheReload['full'] = true;
                $this->updateStatus($manuscript, $data);
            }
            if (property_exists($data, 'illustrated')) {
                if (!is_bool($data->illustrated)) {
                    throw new BadRequestHttpException('Incorrect illustrated data.');
                }
                $cacheReload['full'] = true;
                $this->updateIllustrated($manuscript, $data->illustrated);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new manuscript data
            $this->clearCache($id, $cacheReload);
            $newManuscript = $this->getFull($id);

            $this->updateModified($new ? null : $manuscript, $newManuscript);

            // (re-)index in elastic search
            $this->ess->add($newManuscript);

            // update Elastic occurrences
            if ($cacheReload['mini']) {
                $occurrences = $this->container->get('occurrence_manager')->getManuscriptDependencies($id);
                $this->container->get('occurrence_manager')->elasticIndex($occurrences);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset cache and elasticsearch on elasticsearch error
            if (isset($newManuscript)) {
                $this->reset([$id]);
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

    public function delete(int $manuscriptId): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $manuscript = $this->getFull($manuscriptId);

            $this->dbs->delete($manuscriptId);

            $this->updateModified($manuscript, null);

            // empty cache
            $this->clearCache();

            // delete from elastic search
            $this->ess->delete($manuscript);

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
