<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Origin;

class OriginManager extends ObjectManager
{
    public function getAllOrigins(): array
    {
        // TODO: debug cache issue when editing the city name of one origin and then the city name of another origin
        // (after cache clear)
        $cache = $this->cache->getItem('origins');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $origins = [];
        $rawOrigins = $this->dbs->getOriginIds();
        $originIds = self::getUniqueIds($rawOrigins, 'origin_id');
        $locations = $this->container->get('location_manager')->getLocationsByIds($originIds);
        foreach ($locations as $location) {
            $origins[$location->getId()] = Origin::fromLocation($location);
        }

        usort($origins, ['AppBundle\Model\Location', 'sortByHistoricalName']);

        $cache->tag(['regions', 'institutions']);
        $this->cache->save($cache->set($origins));
        return $origins;
    }
}
