<?php

namespace AppBundle\ObjectStorage;

use stdClass;

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

            $regions = [];
            foreach (array_keys($ids) as $key) {
                $regions[] = new Region($ids[$key], $names[$key], $historicalNames[$key]);
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

    public function getRegionsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'region');
        if (empty($ids)) {
            return $cached;
        }

        $regions = [];
        $rawRegions = $this->dbs->getRegionsByIds($ids);

        foreach ($rawRegions as $rawRegion) {
            $regions[$rawRegion['region_id']] = new Region($rawRegion['region_id'], $rawRegion['name'], $rawRegion['historical_name']);
        }

        $this->setCache($regions, 'region');

        return $cached + $regions;
    }

    public function updateRegion(int $id, stdClass $data): ?Region
    {
        $this->dbs->beginTransaction();
        try {
            $regions = $this->getRegionsByIds([$id]);
            if (count($regions) == 0) {
                $this->dbs->rollBack();
                return null;
            }
            $region = $regions[$id];

            // update region data
            if (property_exists($data, 'name')) {
                $this->dbs->updateName($id, $data->name);
            }

            // load new region data
            $this->cache->invalidateTags(['regions']);
            $this->cache->deleteItem('region.' . $id);
            $newRegion = $this->getRegionsByIds([$id])[$id];

            $this->updateModified($region, $newRegion);

            // update cache
            $this->setCache([$newRegion->getId() => $newRegion], 'region');

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegion;
    }
}
