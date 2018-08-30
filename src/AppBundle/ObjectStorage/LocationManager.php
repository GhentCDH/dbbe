<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Location;

class LocationManager extends ObjectManager
{
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Location::CACHENAME,
            $ids,
            function ($ids) {
                $locations = [];
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

                return $locations;
            }
        );
    }

    public function getLocationsForManuscripts(): array
    {
        return $this->wrapArrayCache(
            'locations_for_manuscripts',
            ['regions', 'institutions', 'collections'],
            function () {
                $rawLocations = $this->dbs->getLocationIdsForManuscripts();
                $locationIds = self::getUniqueIds($rawLocations, 'location_id');
                $locations = $this->get($locationIds);

                // Sort by name
                usort($locations, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $locations;
            }
        );
    }

    public function getLocationsForLocations(): array
    {
        return $this->wrapArrayCache(
            'locations_for_locations',
            ['regions', 'institutions', 'collections'],
            function () {
                $rawLocations = $this->dbs->getLocationIdsForLocations();
                $locationIds = self::getUniqueIds($rawLocations, 'location_id');
                $locations = $this->get($locationIds);

                // Sort by name
                usort($locations, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $locations;
            }
        );
    }

    public function getLocationByRegion(int $regionId): int
    {
        return $this->dbs->getLocationByRegion($regionId);
    }
}
