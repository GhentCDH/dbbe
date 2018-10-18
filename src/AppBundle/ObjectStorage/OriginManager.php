<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Origin;

use AppBundle\Utils\ArrayToJson;

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

    public function getByType(string $type): array
    {
        switch ($type) {
            case 'manuscript':
                $rawOrigins = $this->dbs->getOriginIdsForManuscripts();
                break;
            case 'person':
                $rawOrigins = $this->dbs->getOriginIdsForPersons();
                break;
        }
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

    public function getByTypeShortJson(string $type): array
    {
        return $this->wrapArrayTypeCache(
            $type . '_origins',
            $type,
            ['regions', 'institutions'],
            function ($type) {
                return ArrayToJson::arrayToShortJson($this->getByType($type));
            }
        );
    }

    public function getByTypeJson(string $type): array
    {
        return ArrayToJson::arrayToJson($this->getByType($type));
    }
}
