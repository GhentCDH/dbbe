<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Collection;
use AppBundle\Model\Library;
use AppBundle\Model\Location;
use AppBundle\Model\Region;

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

        foreach ($rawLocations as $rawLocation) {
            $locations[$rawLocation['location_id']] = (new Location())
                ->setId($rawLocation['location_id'])
                ->setCity(new Region($rawLocation['city_id'], $rawLocation['city_name']), null)
                ->addCacheDependency('region.' . $rawLocation['city_id'])
                ->setLibrary(new Library($rawLocation['library_id'], $rawLocation['library_name']))
                ->addCacheDependency('library.' . $rawLocation['library_id'])
                ->setShelf($rawLocation['shelf']);
            if (isset($rawLocation['collection_id'])) {
                $locations[$rawLocation['location_id']]
                    ->setCollection(new Collection($rawLocation['collection_id'], $rawLocation['collection_name']))
                    ->addCacheDependency('collection.' . $rawLocation['collection_id']);
            }
        }

        $this->setCache($locations, 'location');

        return $locations;
    }

    public function getAllCities(): array
    {
        $cache = $this->cache->getItem('cities');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawCities = $this->dbs->getAllCities();
        $cities = [];
        foreach ($rawCities as $rawCity) {
            $cities[$rawCity['city_id']] = new Region($rawCity['city_id'], $rawCity['city_name']);
        }

        $cache->tag('regions');
        $this->cache->save($cache->set($cities));
        return $cities;
    }

    public function getLibrariesInCity(int $city_id): array
    {
        $cache = $this->cache->getItem('libraries_in_city.' . $city_id);
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawLibraries = $this->dbs->getLibrariesInCity($city_id);
        $libraries = [];
        foreach ($rawLibraries as $rawLibrary) {
            $libraries[$rawLibrary['library_id']] = new Library($rawLibrary['library_id'], $rawLibrary['library_name']);
        }

        $cache->tag('libraries');
        $this->cache->save($cache->set($libraries));
        return $libraries;
    }

    public function getCollectionsInLibrary(int $library_id): array
    {
        $cache = $this->cache->getItem('collections_in_library.' . $library_id);
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawCollections = $this->dbs->getCollectionsInLibrary($library_id);
        $collections = [];
        foreach ($rawCollections as $rawCollection) {
            $collections[$rawCollection['collection_id']] =
                new Collection($rawCollection['collection_id'], $rawCollection['collection_name']);
        }

        $cache->tag('libraries');
        $this->cache->save($cache->set($collections));
        return $collections;
    }

    public function updateLocation(Location $location): void
    {
        if (!empty($location->getCollection())) {
            $this->dbs->updateCollection($location->getId(), $location->getCollection()->getId());
        } else {
            $this->dbs->updateLibrary($location->getId(), $location->getLibrary()->getId());
        }
    }

    public function updateShelf(Location $location): void
    {
        $this->dbs->updateShelf($location->getId(), $location->getShelf());
    }
}
