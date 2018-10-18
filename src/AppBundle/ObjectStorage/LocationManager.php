<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Location;

use AppBundle\Utils\ArrayToJson;

class LocationManager extends ObjectManager
{
    public function get(array $ids): array
    {
        $locations = [];
        if (!empty($ids)) {
            $rawLocations = $this->dbs->getLocationsByIds($ids);
            $regionIds = self::getUniqueIds($rawLocations, 'region_id');
            $regionsWithParents = $this->container->get('region_manager')->getWithParents($regionIds);
            $institutions = $this->container->get('institution_manager')->getWithData($rawLocations);
            $collections = $this->container->get('collection_manager')->getWithData($rawLocations);

            foreach ($rawLocations as $rawLocation) {
                 $location = (new Location())
                    ->setId($rawLocation['location_id'])
                    ->setRegionWithParents($regionsWithParents[$rawLocation['region_id']]);

                if (isset($rawLocation['institution_id'])) {
                    $location->setInstitution($institutions[$rawLocation['institution_id']]);
                }
                if (isset($rawLocation['collection_id'])) {
                    $location->setCollection($collections[$rawLocation['collection_id']]);
                }

                $locations[$rawLocation['location_id']] = $location;
            }
        }

        return $locations;
    }

    private function getByType(string $type): array
    {
        switch ($type) {
            case 'manuscript':
                $rawIds = $this->dbs->getLocationIdsForManuscripts();
                break;
            case 'location':
                $rawIds = $this->dbs->getLocationIdsForLocations();
                break;
        }
        $locationIds = self::getUniqueIds($rawIds, 'location_id');
        $locations = $this->get($locationIds);

        // Sort by name
        usort($locations, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $locations;
    }

    public function getByTypeJson(string $type): array
    {
        return $this->wrapArrayTypeCache(
            $type . '_locations',
            $type,
            ['regions', 'institutions', 'collections'],
            function ($type) {
                return ArrayToJson::arrayToJson($this->getByType($type));
            }
        );
    }

    public function getLocationByRegion(int $regionId): int
    {
        return $this->dbs->getLocationByRegion($regionId);
    }
}
