<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use AppBundle\Model\Collection;
use AppBundle\Model\Institution;
use AppBundle\Model\Location;

class LocationManager extends ObjectManager
{
    public function getLocationsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'location');
        if (empty($ids)) {
            return $cached;
        }

        $locations = [];
        $rawLocations = $this->dbs->getLocationsByIds($ids);
        $regionIds = self::getUniqueIds($rawLocations, 'region_id');
        $regionsWithParents = $this->container->get('region_manager')->getRegionsWithParentsByIds($regionIds);

        foreach ($rawLocations as $rawLocation) {
             $location = (new Location())
                ->setId($rawLocation['location_id'])
                ->setRegionWithParents($regionsWithParents[$rawLocation['region_id']]);

            if (isset($rawLocation['institution_id'])) {
                $location->setInstitution(new Institution($rawLocation['institution_id'], $rawLocation['institution_name']));
            }
            if (isset($rawLocation['collection_id'])) {
                $location->setCollection(new Collection($rawLocation['collection_id'], $rawLocation['collection_name']));
            }

            $locations[$rawLocation['location_id']] = $location;
        }

        $this->setCache($locations, 'location');
        return $cached + $locations;
    }

    public function getLocationsForManuscripts(): array
    {
        $cache = $this->cache->getItem('locations_for_manuscripts');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawLocationsForManuscripts = $this->dbs->getLocationIdsForManuscripts();
        $locationIds = self::getUniqueIds($rawLocationsForManuscripts, 'location_id');
        $locationsForManuscripts = $this->getLocationsByIds($locationIds);

        // Sort by name
        usort($locationsForManuscripts, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['regions', 'institutions', 'collections']);
        $this->cache->save($cache->set($locationsForManuscripts));
        return $locationsForManuscripts;
    }

    public function getLocationsForLocations(): array
    {
        $cache = $this->cache->getItem('locations_for_locations');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawLocationsForLocations = $this->dbs->getLocationIdsForLocations();
        $locationIds = self::getUniqueIds($rawLocationsForLocations, 'location_id');
        $locationsForLocations = $this->getLocationsByIds($locationIds);

        // Sort by name
        usort($locationsForLocations, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['regions', 'institutions', 'collections']);
        $this->cache->save($cache->set($locationsForLocations));
        return $locationsForLocations;
    }

    public function getLocationByRegion(int $regionId): int
    {
        return $this->dbs->getLocationByRegion($regionId);
    }
}
