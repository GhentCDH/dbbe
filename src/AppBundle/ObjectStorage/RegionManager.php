<?php

namespace AppBundle\ObjectStorage;

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
}
