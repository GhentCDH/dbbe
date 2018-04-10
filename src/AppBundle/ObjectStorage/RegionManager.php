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
    public function getRegionsWithParentsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'region_with_parents');
        if (empty($ids)) {
            return $cached;
        }

        $regionsWithParents = [];
        $rawRegionsWithParents = $this->dbs->getRegionsWithParentsByIds($ids);

        foreach ($rawRegionsWithParents as $rawRegionWithParents) {
            $ids = explode(':', $rawRegionWithParents['ids']);
            $names = explode(':', $rawRegionWithParents['names']);
            $historicalNames = explode(':', $rawRegionWithParents['historical_names']);
            $isCities = explode(':', $rawRegionWithParents['is_cities']);
            $pleiadesIds = explode(':', $rawRegionWithParents['pleiades_ids']);

            $regions = [];
            foreach (array_keys($ids) as $key) {
                $regions[] = new Region(
                    (int)$ids[$key],
                    $names[$key],
                    $historicalNames[$key],
                    $isCities[$key] === 'true',
                    $pleiadesIds[$key] == '' ? null : (int)$pleiadesIds[$key]
                );
            }
            $regionWithParents = new RegionWithParents($regions);

            foreach ($ids as $id) {
                $regionWithParents->addCacheDependency('region.' . $id);
            }

            $regionsWithParents[$regionWithParents->getId()] = $regionWithParents;
        }

        $this->setCache($regionsWithParents, 'region_with_parents');

        return $cached + $regionsWithParents;
    }

    public function getAllRegionsWithParents(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'region_id');
        return $this->getRegionsWithParentsByIds($ids);
    }

    public function getRegionsWithParentsByRegion(int $regionId): array
    {
        $rawIds = $this->dbs->getRegionsByRegion($regionId);
        $ids = self::getUniqueIds($rawIds, 'region_id');
        return $this->getRegionsWithParentsByIds($ids);
    }

    public function getRegionsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'region');
        if (empty($ids)) {
            return $cached;
        }

        $regions = [];
        $rawRegions = $this->dbs->getRegionsByIds($ids);

        foreach ($rawRegions as $rawRegion) {
            $regions[$rawRegion['region_id']] = new Region(
                $rawRegion['region_id'],
                $rawRegion['name'],
                $rawRegion['historical_name'],
                $rawRegion['is_city'],
                $rawRegion['pleiades_id']
            );
        }

        $this->setCache($regions, 'region');

        return $cached + $regions;
    }

    public function addRegionWithParents(stdClass $data): RegionWithParents
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'parent')
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
                && property_exists($data, 'individualName')
                && is_string($data->individualName)
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
                    $data->parent->id,
                    $data->individualName,
                    property_exists($data, 'historicalName') ? $data->historicalName : null,
                    (property_exists($data, 'isCity') && is_bool($data->isCity)) ? $data->isCity : false
                );
                if (property_exists($data, 'pleiades') && is_numeric($data->pleiades)) {
                    $this->dbs->upsertPleiades($regionId, $data->pleiades);
                }
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new region data
            $this->cache->invalidateTags(['regions']);
            $newRegionWithParents = $this->getRegionsWithParentsByIds([$regionId])[$regionId];

            $this->updateModified(null, $newRegionWithParents);

            // update cache
            $this->setCache([$newRegionWithParents->getId() => $newRegionWithParents], 'region_with_parents');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegionWithParents;
    }

    public function updateRegionWithParents(int $id, stdClass $data): RegionWithParents
    {
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getRegionsWithParentsByIds([$id]);
            if (count($regionsWithParents) == 0) {
                $this->dbs->rollBack();
                return null;
            }
            $regionWithParents = $regionsWithParents[$id];

            // update region data
            $correct = false;
            if (property_exists($data, 'parent')
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
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
            $this->cache->invalidateTags(['regions']);
            $this->cache->deleteItem('region.' . $id);
            $this->cache->deleteItem('region_with_parents.' . $id);
            $newRegionWithParents = $this->getRegionsWithParentsByIds([$id])[$id];

            $this->updateModified($regionWithParents, $newRegionWithParents);

            // update cache
            $this->setCache([$newRegionWithParents->getId() => $newRegionWithParents], 'region_with_parents');

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegionWithParents;
    }

    public function delRegion(int $regionId): void
    {
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getRegionsWithParentsByIds([$regionId]);
            if (count($regionsWithParents) == 0) {
                throw new NotFoundHttpException('Region with id ' . $regionId .' not found.');
            }
            $regionWithParents = $regionsWithParents[$regionId];

            $this->dbs->delete($regionId);

            // load new region data
            $this->cache->invalidateTags(['regions']);
            $this->cache->deleteItem('region.' . $regionId);
            $this->cache->deleteItem('region_with_parents.' . $regionId);

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
