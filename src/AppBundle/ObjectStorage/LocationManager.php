<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use AppBundle\Model\Collection;
use AppBundle\Model\Document;
use AppBundle\Model\Institution;
use AppBundle\Model\Library;
use AppBundle\Model\Location;
use AppBundle\Model\Origin;
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
                ->setCity(new Region($rawLocation['city_id'], $rawLocation['city_name']), null, null, null)
                ->addCacheDependency('region.' . $rawLocation['city_id'])
                ->setLibrary(new Library($rawLocation['library_id'], $rawLocation['library_name']))
                ->addCacheDependency('institution.' . $rawLocation['library_id'])
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

    public function getAllCitiesLibrariesCollections(): array
    {
        $cache = $this->cache->getItem('citiesLibrariesCollections');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $citiesLibrariesCollections = $this->dbs->getAllCitiesLibrariesCollections();
        $regionIds = self::getUniqueIds($citiesLibrariesCollections, 'city_id');
        $regionsWithParents = $this->oms['region_manager']->getRegionsWithParentsByIds($regionIds);

        foreach ($citiesLibrariesCollections as $key => $value) {
            $citiesLibrariesCollections[$key]['city_name'] = $regionsWithParents[$value['city_id']]->getName();
            $citiesLibrariesCollections[$key]['city_individualName'] = $regionsWithParents[$value['city_id']]->getIndividualName();
            // Remove cities with no name
            if ($citiesLibrariesCollections[$key]['city_name'] == '') {
                unset($citiesLibrariesCollections[$key]);
            }
        }

        usort($citiesLibrariesCollections, ['AppBundle\Model\Location', 'sortRaw']);

        $cache->tag('regions');
        $cache->tag('institutions');
        $cache->tag('collections');
        $this->cache->save($cache->set($citiesLibrariesCollections));
        return $citiesLibrariesCollections;
    }

    public function getAllOrigins(): array
    {
        $cache = $this->cache->getItem('origins');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawOrigins = $this->dbs->getAllOrigins();
        $regionIds = self::getUniqueIds($rawOrigins, 'region_id');
        $regionsWithParents = $this->oms['region_manager']->getRegionsWithParentsByIds($regionIds);
        $origins = [];
        foreach ($rawOrigins as $rawOrigin) {
            $origin = (new Origin())
                ->setId($rawOrigin['origin_id'])
                ->setRegionWithParents($regionsWithParents[$rawOrigin['region_id']]);
            if (isset($rawOrigin['institution_id'])) {
                $origin->setInstitution(
                    new Institution($rawOrigin['institution_id'], $rawOrigin['institution_name'])
                );
            }
            $origins[] = $origin;
        }

        $cache->tag('regions');
        $cache->tag('institutions');
        $this->cache->save($cache->set($origins));
        return $origins;
    }

    public function getLocationsByRegion(int $regionId): array
    {
        $rawLocations = $this->dbs->getLocationsByRegion($regionId);
        $locationIds = self::getUniqueIds($rawLocations, 'location_id');
        return $this->getLocationsByIds($locationIds);
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
