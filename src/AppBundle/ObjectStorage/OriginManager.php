<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Origin;

class OriginManager extends ObjectManager
{
    public function get(array $ids): array
    {
        return array_map(
            function ($location) {
                return Origin::fromLocation($location);
            },
            $this->container->get('location_manager')->get($ids)
        );
    }

    public function getAllOrigins(): array
    {
        return $this->wrapArrayCache(
            'origins',
            ['locations'],
            function () {
                $origins = [];
                $rawOrigins = $this->dbs->getOriginIds();
                $originIds = self::getUniqueIds($rawOrigins, 'origin_id');
                $locations = $this->container->get('location_manager')->get($originIds);
                foreach ($locations as $location) {
                    $origins[$location->getId()] = Origin::fromLocation($location);
                }

                // Sort by name
                usort($origins, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $origins;
            }
        );
    }
}
