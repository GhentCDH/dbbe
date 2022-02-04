<?php

namespace App\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Model\Manuscript;
use App\Model\Origin;
use App\Model\Status;

class ManuscriptManager extends DocumentManager
{
    /**
     * Get manuscripts with enough information to get an id and a name
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $manuscripts = [];
        // LocatedAts
        // locatedAts are identifiedd by document ids
        $locatedAts = $this->container->get(LocatedAtManager::class)->get($ids);
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

    /**
     * Get manuscripts with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $manuscripts = $this->getMini($ids);

        // Remove all ids that did not match above
        $ids = array_keys($manuscripts);

        // Contents
        $rawContents = $this->dbs->getContents($ids);
        if (count($rawContents) > 0) {
            $contentIds = self::getUniqueIds($rawContents, 'genre_id');
            $contentsWithParents = $this->container->get(ContentManager::class)->getWithParents($contentIds);
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
            $persons = $this->container->get(PersonManager::class)->getShort($personIds);
        }
        $occurrences = [];
        if (count($occurrenceIds) > 0) {
            $occurrences = $this->container->get(OccurrenceManager::class)->getMini($occurrenceIds);
        }

        $roles = $this->container->get(RoleManager::class)->getWithData($rawOccurrenceRoles);

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
            $locations = $this->container->get(LocationManager::class)->get($locationIds);

            foreach ($rawOrigins as $rawOrigin) {
                $manuscripts[$rawOrigin['manuscript_id']]
                    ->setOrigin(Origin::fromLocation($locations[$rawOrigin['location_id']]));
            }
        }

        // Occurrences
        $rawOccurrences = $this->dbs->getOccurrences($ids);
        if (count($rawOccurrences) > 0) {
            $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
            $occurrences = $this->container->get(OccurrenceManager::class)->getMini($occurrenceIds);
            foreach ($rawOccurrences as $rawOccurrence) {
                $manuscripts[$rawOccurrence['manuscript_id']]
                    ->addOccurrence($occurrences[$rawOccurrence['occurrence_id']]);
            }
        }

        $this->setAcknowledgements($manuscripts);

        $this->setIdentifications($manuscripts);

        $this->setComments($manuscripts);

        $this->setcontributorRoles($manuscripts);

        $this->setManagements($manuscripts);

        $this->setCreatedAndModifiedDates($manuscripts);

        return $manuscripts;
    }

    /**
     * Get a single manuscript with all information
     * @param  int        $id
     * @return Manuscript
     */
    public function getFull(int $id): Manuscript
    {
        // Get basic manuscript information
        $manuscripts = $this->getShort([$id]);

        if (count($manuscripts) == 0) {
            throw new NotFoundHttpException('Manuscript with id ' . $id .' not found.');
        }

        $this->setBibliographies($manuscripts);

        $manuscript = $manuscripts[$id];

        // status
        $rawStatuses = $this->dbs->getStatuses([$id]);
        if (count($rawStatuses) == 1) {
            $statuses = $this->container->get(StatusManager::class)->getWithData($rawStatuses);
            $manuscript->setStatus($statuses[$rawStatuses[0]['status_id']]);
        }

        // Illustrated
        $rawIllustrateds = $this->dbs->getIllustrateds([$id]);
        if (count($rawIllustrateds) == 1) {
            $manuscript->setIllustrated($rawIllustrateds[0]['illustrated']);
        }

        return $manuscript;
    }

    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return parent::getAllMiniShortJson($sortFunction == null ? 'getDescription' : $sortFunction);
    }

    public function getNewId(int $oldId): int
    {
        $rawId = $this->dbs->getNewId($oldId);
        if (count($rawId) != 1) {
            throw new NotFoundHttpException('The manuscript with legacy id "' . $oldId . '" does not exist.');
        }
        return $rawId[0]['new_id'];
    }

    public function getRegionDependencies(int $regionId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionId($regionId), $method);
    }

    public function getRegionDependenciesWithChildren(int $regionId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionIdWithChildren($regionId), $method);
    }

    public function getInstitutionDependencies(int $institutionId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByInstitutionId($institutionId), $method);
    }

    public function getCollectionDependencies(int $collectionId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByCollectionId($collectionId), $method);
    }

    public function getContentDependencies(int $contentId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByContentId($contentId), $method);
    }

    public function getContentDependenciesWithChildren(int $contentId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByContentIdWithChildren($contentId), $method);
    }

    public function getPersonContentDependenciesWithChildren(int $personId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonContentIdWithChildren($personId), $method);
    }

    public function getOccurrenceDependencies(int $occurrenceId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOccurrenceId($occurrenceId), $method);
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
            $this->container->get(LocatedAtManager::class)->addLocatedAt(
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
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            $changes = [
                'mini' => $isNew,
                'short' => $isNew,
                'full' => $isNew,
            ];
            if (property_exists($data, 'locatedAt')) {
                $changes['mini'] = true;
                $this->container->get(LocatedAtManager::class)->updateLocatedAt(
                    $old->getLocatedAt()->getId(),
                    $data->locatedAt
                );
            }
            if (property_exists($data, 'public')) {
                $changes['mini'] = true;
                $this->updatePublic($old, $data->public);
            }
            if (property_exists($data, 'contents')) {
                $changes['short'] = true;
                $this->updateContents($old, $data->contents);
            }
            $roles = $this->container->get(RoleManager::class)->getByType('manuscript');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['short'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'dates')) {
                $this->validateDates($data->dates);
                $changes['mini'] = true;
                $this->updateDates($old, $data->dates);
            }
            if (property_exists($data, 'origin')) {
                $changes['short'] = true;
                $this->updateOrigin($old, $data->origin);
            }
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePublicComment($id, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }
            if (property_exists($data, 'occurrenceOrder')) {
                $changes['full'] = true;
                $this->updateOccurrenceOrder($old, $data->occurrenceOrder);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'manuscript');
            if (property_exists($data, 'bibliography')) {
                if (!is_object($data->bibliography)) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                $changes['full'] = true;
                $this->updateBibliography($old, $data->bibliography);
            }
            if (property_exists($data, 'acknowledgements')) {
                if (!is_array($data->acknowledgements)) {
                    throw new BadRequestHttpException('Incorrect acknowledgements data.');
                }
                $changes['short'] = true;
                $this->updateAcknowledgements($old, $data->acknowledgements);
            }
            if (property_exists($data, 'status')) {
                if (!(is_object($data->status) || empty($data->status))) {
                    throw new BadRequestHttpException('Incorrect text status data.');
                }
                $changes['full'] = true;
                $this->updateStatus($old, $data->status, Status::MANUSCRIPT);
            }
            if (property_exists($data, 'illustrated')) {
                if (!is_bool($data->illustrated)) {
                    throw new BadRequestHttpException('Incorrect illustrated data.');
                }
                $changes['full'] = true;
                $this->updateIllustrated($old, $data->illustrated);
            }
            $contributorRoles = $this->container->get(RoleManager::class)->getContributorByType('manuscript');
            foreach ($contributorRoles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['short'] = true;
                    $this->updateContributorRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // (re-)index in elastic search
            if ($changes['mini'] || $changes['short']) {
                $this->ess->add($new);
            }

            // update Elastic occurrences
            if ($changes['mini'] || $changes['short']) {
                $this->container->get(OccurrenceManager::class)->updateElasticByIds(
                    $this->container->get(OccurrenceManager::class)->getManuscriptDependencies($id, 'getId')
                );
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch on elasticsearch error
            if ($isNew) {
                $this->updateElasticByIds([$id]);
            } elseif (isset($new) && isset($old)) {
                $this->ess->add($old);

                // New manuscripts cannot have occurrence dependencies
                // Elastic occurrences are only updated when mini information is modified
                if (isset($changes) && ($changes['mini'] || $changes['short'])) {
                    $this->container->get(OccurrenceManager::class)->updateElasticByIds(
                        $this->container->get(OccurrenceManager::class)->getManuscriptDependencies($id, 'getId')
                    );
                }
            }

            throw $e;
        }

        return $new;
    }

    private function updateContents(Manuscript $manuscript, array $contents): void
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
}
