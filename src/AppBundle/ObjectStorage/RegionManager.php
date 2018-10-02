<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Region;
use AppBundle\Model\RegionWithParents;

/**
 * ObjectManager for regions
 * Servicename: region_manager
 */
class RegionManager extends ObjectManager
{
    /**
     * Get single regions with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Region::CACHENAME,
            $ids,
            function ($ids) {
                $regions = [];
                $rawRegions = $this->dbs->getRegionsByIds($ids);
                $regions = $this->getWithData($rawRegions);

                return $regions;
            }
        );
    }

    /**
     * Get single regions with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            Region::CACHENAME,
            $data,
            'region_id',
            function ($data) {
                $regions = [];
                foreach ($data as $rawRegion) {
                    if (isset($rawRegion['region_id']) && !isset($regions[$rawRegion['region_id']])) {
                        $regions[$rawRegion['region_id']] = new Region(
                            $rawRegion['region_id'],
                            $rawRegion['name'],
                            $rawRegion['historical_name'],
                            $rawRegion['is_city'],
                            $rawRegion['pleiades_id'] == '' ? null : (int)$rawRegion['pleiades_id']
                        );
                    }
                }

                return $regions;
            }
        );
    }

    /**
     * Get regions with parents with all information
     * @param  array $ids
     * @return array
     */
    public function getWithParents(array $ids): array
    {
        return $this->wrapCache(
            RegionWithParents::CACHENAME,
            $ids,
            function ($ids) {
                $regionsWithParents = [];
                $rawRegionsWithParents = $this->dbs->getRegionsWithParentsByIds($ids);

                foreach ($rawRegionsWithParents as $rawRegionWithParents) {
                    $ids = json_decode($rawRegionWithParents['ids']);
                    $names = json_decode($rawRegionWithParents['names']);
                    $historicalNames = json_decode($rawRegionWithParents['historical_names']);
                    $isCities = json_decode($rawRegionWithParents['is_cities']);
                    $pleiadesIds = json_decode($rawRegionWithParents['pleiades_ids']);

                    $rawRegions = [];
                    foreach (array_keys($ids) as $key) {
                        $rawRegions[] = [
                            'region_id' => (int)$ids[$key],
                            'name' => $names[$key],
                            'historical_name' => $historicalNames[$key],
                            'is_city' => $isCities[$key] === 'true',
                            'pleiades_id' => $pleiadesIds[$key] === '' ? null : (int)$pleiadesIds[$key],
                        ];
                    }

                    $regions = $this->getWithData($rawRegions);

                    $orderedRegions = [];
                    foreach ($ids as $id) {
                        $orderedRegions[] = $regions[(int)$id];
                    }

                    $regionWithParents = new RegionWithParents($orderedRegions);

                    $regionsWithParents[$regionWithParents->getId()] = $regionWithParents;
                }

                return $regionsWithParents;
            }
        );
    }

    /**
     * Get all regions with parents with all information
     * @return array
     */
    public function getAll(): array
    {
        return $this->wrapArrayCache(
            'regions_with_parents',
            ['regions'],
            function () {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, 'region_id');
                $regionsWithParents = $this->getWithParents($ids);

                // Sort by name
                usort($regionsWithParents, function ($a, $b) {
                    return strcmp($a->getNameHistoricalName(), $b->getNameHistoricalName());
                });

                return $regionsWithParents;
            }
        );
    }

    /**
     * Get all regions that are dependent on a specific region
     * @param  int   $regionId
     * @return array
     */
    public function getRegionDependencies(int $regionId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionId($regionId), 'getWithParents');
    }

    /**
     * Clear cache
     * @param array $ids
     */
    public function reset(array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteCache(Region::CACHENAME, $id);
            $this->deleteCache(RegionWithParents::CACHENAME, $id);
        }

        $this->getWithParents($ids);
    }

    /**
     * Add a new region
     * @param  stdClass $data
     * @return RegionWithParents
     */
    public function add(stdClass $data): RegionWithParents
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
                && (
                    !property_exists($data, 'parent')
                    || (
                        $data->parent == null
                        || (property_exists($data->parent, 'id') && is_numeric($data->parent->id))
                    )
                )
                && !(
                    property_exists($data, 'individualHistoricalName')
                    && !($data->individualHistoricalName == null || is_string($data->individualHistoricalName))
                )
                && !(
                    property_exists($data, 'pleiades')
                    && !($data->pleiades == null || is_numeric($data->pleiades))
                )
                && !(
                    property_exists($data, 'isCity')
                    && !($data->isCity == null || is_bool($data->isCity))
                )
            ) {
                $regionId = $this->dbs->insert(
                    (property_exists($data, 'parent') && $data->parent != null) ? $data->parent->id : null,
                    $data->individualName,
                    property_exists($data, 'individualHistoricalName') ? $data->individualHistoricalName : null,
                    (property_exists($data, 'isCity') && is_bool($data->isCity)) ? $data->isCity : false
                );
                if (property_exists($data, 'pleiades') && is_numeric($data->pleiades)) {
                    $this->dbs->upsertPleiades($regionId, $data->pleiades);
                }
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new region data
            $newRegionWithParents = $this->getWithParents([$regionId])[$regionId];

            $this->updateModified(null, $newRegionWithParents);

            // update cache
            $this->cache->invalidateTags(['regions']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegionWithParents;
    }

    /**
     * Update an existing region
     * @param  int      $id
     * @param  stdClass $data
     * @return RegionWithParents
     */
    public function update(int $id, stdClass $data): RegionWithParents
    {
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getWithParents([$id]);
            if (count($regionsWithParents) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Region with id ' . $id .' not found.');
            }
            $regionWithParents = $regionsWithParents[$id];

            // update region data
            $correct = false;
            if (property_exists($data, 'parent')
                && $data->parent == null
            ) {
                $correct = true;
                $this->dbs->updateParent($id, null);
            }
            if (property_exists($data, 'parent')
                && $data->parent != null
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
                // Prevent cycles
                && $data->parent->id != $id
                // Prevent cycles
                && !in_array($data->parent->id, self::getUniqueIds($this->dbs->getChildIds($id), 'child_id'))
            ) {
                $correct = true;
                $this->dbs->updateParent($id, $data->parent->id);
            }
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
            ) {
                $correct = true;
                $this->dbs->updateName($id, $data->individualName);
            }
            if (property_exists($data, 'individualHistoricalName')
                && is_string($data->individualHistoricalName)
            ) {
                $correct = true;
                $this->dbs->updateHistoricalName($id, $data->individualHistoricalName);
            }
            if (property_exists($data, 'pleiades')
                && is_numeric($data->pleiades)
            ) {
                $correct = true;
                $this->dbs->upsertPleiades($id, $data->pleiades);
            }
            if (property_exists($data, 'isCity')
                && is_bool($data->isCity)
            ) {
                $correct = true;
                $this->dbs->updateIsCity($id, $data->isCity);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new region data
            $this->deleteCache(Region::CACHENAME, $id);
            $this->deleteCache(RegionWithParents::CACHENAME, $id);
            $newRegionWithParents = $this->getWithParents([$id])[$id];

            $this->updateModified($regionWithParents, $newRegionWithParents);

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getRegionDependenciesWithChildren($id, true);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            // Update Elastic persons
            // via office
            $officesWithParents = $this->container->get('office_manager')->getRegionDependenciesWithChildren($id);
            $persons = [];
            foreach ($officesWithParents as $officesWithParent) {
                $persons += $this->container->get('person_manager')->getOfficeDependenciesWithChildren(
                    $officesWithParent->getId(),
                    true
                );
            }

            // via origin
            $persons += $this->container->get('person_manager')->getRegionDependenciesWithChildren($id, true);
            $this->container->get('person_manager')->elasticIndex($persons);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegionWithParents;
    }

    /**
     * Merge two regions
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return RegionWithParents
     */
    public function merge(int $primaryId, int $secondaryId): RegionWithParents
    {
        $regionsWithParents = $this->getWithParents([$primaryId, $secondaryId]);
        if (count($regionsWithParents) != 2) {
            if (!array_key_exists($primaryId, $regionsWithParents)) {
                throw new NotFoundHttpException('Region with id ' . $primaryId .' not found.');
            }
            if (!array_key_exists($secondaryId, $regionsWithParents)) {
                throw new NotFoundHttpException('Region with id ' . $secondaryId .' not found.');
            }
            throw new BadRequestHttpException(
                'Regions with id ' . $primaryId .' and id ' . $secondaryId . ' cannot be merged.'
            );
        }
        list($primary, $secondary) = array_values($regionsWithParents);
        $updates = [];
        if (empty($primary->getIndividualName()) && !empty($secondary->getIndividualName())) {
            $updates['individualName'] = $secondary->getIndividualName();
        }
        if (empty($primary->getIndividualHistoricalName()) && !empty($secondary->getIndividualHistoricalName())) {
            $updates['individualHistoricalName'] = $secondary->getIndividualHistoricalName();
        }
        if (empty($primary->getPleiades()) && !empty($secondary->getPleiades())) {
            $updates['pleiades'] = $secondary->getPleiades();
        }

        $manuscripts = $this->container->get('manuscript_manager')->getRegionDependencies($secondaryId, true);
        // Only keep dependencies based on origin
        // Locations of the manuscripts themselves never are regions
        $manuscripts = array_filter($manuscripts, function ($manuscript) use ($secondaryId) {
            if (!empty($manuscript->getOrigin())
                && $manuscript->getOrigin()->getRegionWithParents()->getId() == $secondaryId
            ) {
                return true;
            }
            return false;
        });


        $institutions = $this->container->get('institution_manager')->getInstitutionsByRegion($secondaryId);
        $regions = $this->getRegionDependencies($secondaryId);
        $offices = $this->container->get('office_manager')->getRegionDependencies($secondaryId, true);
        $persons = $this->container->get('person_manager')->getRegionDependencies($secondaryId, true);

        $this->dbs->beginTransaction();
        try {
            if (!empty($updates)) {
                $primary = $this->update($primaryId, json_decode(json_encode($updates)));
            }
            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $this->container->get('manuscript_manager')->update(
                        $manuscript->getId(),
                        json_decode(json_encode([
                            'origin' => [
                                'id' => $this->container->get('location_manager')->getLocationByRegion($primaryId)
                            ]
                        ]))
                    );
                }
            }
            if (!empty($institutions)) {
                foreach ($institutions as $institution) {
                    $this->container->get('institution_manager')->updateInstitution(
                        $institution->getId(),
                        json_decode(json_encode(['regionWithParents' => ['id' => $primaryId]]))
                    );
                }
            }
            if (!empty($regions)) {
                foreach ($regions as $region) {
                    $this->update(
                        $region->getId(),
                        json_decode(json_encode(['parent' => ['id' => $primaryId]]))
                    );
                }
            }
            if (!empty($offices)) {
                foreach ($offices as $office) {
                    $this->container->get('office_manager')->update(
                        $office->getId(),
                        json_decode(json_encode(['individualRegionWithParents' => ['id' => $primaryId]]))
                    );
                }
            }
            if (!empty($persons)) {
                foreach ($persons as $person) {
                    $this->container->get('person_manager')->update(
                        $person->getId(),
                        json_decode(json_encode(['region' => ['id' => $primaryId]]))
                    );
                }
            }
            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            // Reset caches and elasticsearch
            $this->reset([$primaryId]);
            $this->cache->invalidateTags(['regions']);
            if (!empty($manuscripts)) {
                $this->container->get('manuscript_manager')->reset(self::getIds($manuscripts));
            }
            if (!empty($institutions)) {
                $this->container->get('institution_manager')->reset(self::getIds($institutions));
            }
            // TODO: reset persons and offices
            if (!empty($regions)) {
                $this->reset(self::getIds($regions));
            }
            throw $e;
        }

        return $primary;
    }

    /**
     * Delete a region
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getWithParents([$id]);
            if (count($regionsWithParents) == 0) {
                throw new NotFoundHttpException('Region with id ' . $id .' not found.');
            }
            $regionWithParents = $regionsWithParents[$id];

            $this->dbs->delete($id);

            // empty cache
            $this->deleteCache(Region::CACHENAME, $id);
            $this->deleteCache(RegionWithParents::CACHENAME, $id);
            $this->cache->invalidateTags(['regions']);

            $this->updateModified($regionWithParents, null);

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
