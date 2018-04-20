<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Origin;

class OriginManager extends ObjectManager
{
    public function getAllOrigins(): array
    {
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

        // Sort by name
        usort($origins, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['regions', 'institutions']);
        $this->cache->save($cache->set($origins));
        return $origins;
    }
}
