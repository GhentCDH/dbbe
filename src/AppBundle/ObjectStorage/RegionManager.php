<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Region;
use AppBundle\Model\RegionWithParents;

class RegionManager extends ObjectManager
{
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Region::CACHENAME,
            $ids,
            function ($ids) {
                $regions = [];
                $rawRegions = $this->dbs->getRegionsByIds($ids);
                $regions = $this->getWithData($rawRegions);

                return $regionsWithParents;
            }
        );
    }

    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            Region::CACHENAME,
            $data,
            'region_id',
            function ($data) {
                $regions = [];
                foreach ($data as $rawRegion) {
                    if (isset($rawRegion['region_id'])) {
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

    public function getWithParents(array $ids): array
    {
        return $this->wrapCache(
            RegionWithParents::CACHENAME,
            $ids,
            function ($ids) {
                $regionsWithParents = [];
                $rawRegionsWithParents = $this->dbs->getRegionsWithParentsByIds($ids);

                foreach ($rawRegionsWithParents as $rawRegionWithParents) {
                    $ids = explode(':', $rawRegionWithParents['ids']);
                    $names = explode(':', $rawRegionWithParents['names']);
                    $historicalNames = explode(':', $rawRegionWithParents['historical_names']);
                    $isCities = explode(':', $rawRegionWithParents['is_cities']);
                    $pleiadesIds = explode(':', $rawRegionWithParents['pleiades_ids']);

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

    public function getAllRegionsWithParents(): array
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

    public function getRegionsWithParentsByRegion(int $regionId): array
    {
        $rawIds = $this->dbs->getRegionsByRegion($regionId);
        $ids = self::getUniqueIds($rawIds, 'region_id');
        $regionsWithParents = $this->getWithParents($ids);

        // Sort by name
        usort($regionsWithParents, function ($a, $b) {
            return strcmp($a->getNameHistoricalName(), $b->getNameHistoricalName());
        });

        return $regionsWithParents;
    }

    /**
     * Clear cache
     * @param array $ids manuscript ids
     */
    public function reset(array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteCache(Region::CACHENAME, $id);
            $this->deleteCache(RegionWithParents::CACHENAME, $id);
        }

        $this->getWithParents($ids);

        $this->cache->invalidateTags(['regions']);
    }

    public function addRegionWithParents(stdClass $data): RegionWithParents
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
                && !(
                    property_exists($data, 'parent')
                    && !(
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

    public function updateRegionWithParents(int $regionId, stdClass $data): RegionWithParents
    {
        // TODO: make sure if a parent changes, the child changes as well
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getWithParents([$regionId]);
            if (count($regionsWithParents) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Region with id ' . $regionId .' not found.');
            }
            $regionWithParents = $regionsWithParents[$regionId];

            // update region data
            $correct = false;
            if (property_exists($data, 'parent')
                && $data->parent == null
            ) {
                $correct = true;
                $this->dbs->updateParent($regionId, null);
            }
            if (property_exists($data, 'parent')
                && $data->parent != null
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
                && $data->parent->id != $regionId
            ) {
                $correct = true;
                $this->dbs->updateParent($regionId, $data->parent->id);
            }
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
            ) {
                $correct = true;
                $this->dbs->updateName($regionId, $data->individualName);
            }
            if (property_exists($data, 'individualHistoricalName')
                && is_string($data->individualHistoricalName)
            ) {
                $correct = true;
                $this->dbs->updateHistoricalName($regionId, $data->individualHistoricalName);
            }
            if (property_exists($data, 'pleiades')
                && is_numeric($data->pleiades)
            ) {
                $correct = true;
                $this->dbs->upsertPleiades($regionId, $data->pleiades);
            }
            if (property_exists($data, 'isCity')
                && is_bool($data->isCity)
            ) {
                $correct = true;
                $this->dbs->updateIsCity($regionId, $data->isCity);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new region data
            $this->deleteCache(Region::CACHENAME, $regionId);
            $this->deleteCache(RegionWithParents::CACHENAME, $regionId);
            $newRegionWithParents = $this->getWithParents([$regionId])[$regionId];

            $this->updateModified($regionWithParents, $newRegionWithParents);

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getRegionDependencies($regionId);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegionWithParents;
    }

    public function mergeRegionsWithParents(int $primaryId, int $secondaryId): RegionWithParents
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
        $regions = $this->getRegionsWithParentsByRegion($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($updates)) {
                $primary = $this->updateRegionWithParents($primaryId, json_decode(json_encode($updates)));
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
                    $this->updateRegionWithParents(
                        $region->getId(),
                        json_decode(json_encode(['parent' => ['id' => $primaryId]]))
                    );
                }
            }
            $this->delRegion($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            // Reset caches and elasticsearch
            // TODO: double check with new caching
            $this->reset([$primaryId]);
            if (!empty($manuscripts)) {
                $this->container->get('manuscript_manager')->reset(array_map(function ($manuscript) {
                    return $manuscript->getId();
                }, $manuscripts));
            }
            if (!empty($institutions)) {
                $this->container->get('institution_manager')->reset(array_map(function ($institution) {
                    return $institution->getId();
                }, $institutions));
            }
            if (!empty($regions)) {
                $this->reset(array_map(function ($region) {
                    return $region->getId();
                }, $regions));
            }
            throw $e;
        }

        return $primary;
    }

    public function delRegion(int $regionId): void
    {
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getWithParents([$regionId]);
            if (count($regionsWithParents) == 0) {
                throw new NotFoundHttpException('Region with id ' . $regionId .' not found.');
            }
            $regionWithParents = $regionsWithParents[$regionId];

            $this->dbs->delete($regionId);

            // empty cache
            $this->cache->invalidateTags(['regions']);
            $this->deleteCache(Region::CACHENAME, $regionId);
            $this->deleteCache(RegionWithParents::CACHENAME, $regionId);

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
