<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use AppBundle\Model\Collection;
use AppBundle\Model\Document;
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

    public function getAllLocations(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'location_id');
        return $this->getLocationsByIds($ids);
    }

    public function getAllCitiesLibrariesCollections(): array
    {
        $cache = $this->cache->getItem('citiesLibrariesCollections');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $citiesLibrariesCollections = $this->dbs->getAllCitiesLibrariesCollections();

        $cache->tag('regions');
        $cache->tag('libraries');
        $cache->tag('collections');
        $this->cache->save($cache->set($citiesLibrariesCollections));
        return $citiesLibrariesCollections;
    }

    public function updateLibrary(Document $document, stdClass $libary): void
    {
        $this->cache->deleteItem('location.' . $document->getId());
        $this->cache->invalidateTags(['location.' . $document->getId()]);
        $this->dbs->updateLibraryId($document->getId(), $libary->id);
    }

    public function updateCollection(Document $document, stdClass $collection): void
    {
        $this->cache->deleteItem('location.' . $document->getId());
        $this->cache->invalidateTags(['location.' . $document->getId()]);
        $this->dbs->updateCollectionId($document->getId(), $collection->id);
    }

    public function updateShelf(Document $document, string $shelf): void
    {
        $this->cache->deleteItem('location.' . $document->getId());
        $this->cache->invalidateTags(['location.' . $document->getId()]);
        $this->dbs->updateShelf($document->getId(), $shelf);
    }
}
