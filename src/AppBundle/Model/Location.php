<?php

namespace AppBundle\Model;

use stdClass;

class Location
{
    use CacheDependenciesTrait;

    /**
     * @var int
     */
    protected $id;
    protected $regionWithParents;
    protected $institution;
    protected $collection;

    public function __construct()
    {
        return $this;
    }

    public function setId(int $id): Location
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setRegionWithParents(RegionWithParents $regionWithParents): Location
    {
        $this->regionWithParents = $regionWithParents;
        $this->addCacheDependency('region_with_parents.' . $regionWithParents->getId());
        foreach ($regionWithParents->getCacheDependencies() as $cacheDependency) {
            $this->addCacheDependency($cacheDependency);
        }

        return $this;
    }

    public function getRegionWithParents(): RegionWithParents
    {
        return $this->regionWithParents;
    }

    public function setInstitution(Institution $institution): Location
    {
        $this->institution = $institution;
        $this->addCacheDependency('institution.' . $institution->getId());

        return $this;
    }

    public function getInstitution(): ?Institution
    {
        return $this->institution;
    }

    public function setCollection(Collection $collection): Location
    {
        $this->collection = $collection;
        $this->addCacheDependency('collection.' . $collection->getId());

        return $this;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'regionWithParents' => $this->regionWithParents->getShortJson(),
        ];
        if (isset($this->institution)) {
            $result['institution'] = $this->institution->getShortJson();
        }
        if (isset($this->collection)) {
            $result['collection'] = $this->collection->getShortJson();
        }
        return $result;
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'regionWithParents' => $this->regionWithParents->getJson(),
        ];
        if (isset($this->institution)) {
            $result['institution'] = $this->institution->getJson();
        }
        if (isset($this->collection)) {
            $result['collection'] = $this->collection->getJson();
        }
        return $result;
    }

    public static function sortByName(Location $a, Location $b): int
    {
        if ($a->getRegionWithParents()->getName() == $b->getRegionWithParents()->getName()) {
            if (!empty($a->getInstitution()) && !empty($b->getInstitution())) {
                if ($a->getInstitution()->getName() == $b->getInstitution()->getName()) {
                    if (!empty($a->getCollection()) && !empty($b->getCollection())) {
                        return strcmp($a->getCollection()->getName(), $b->getCollection()->getName());
                    }
                    if (!empty($a->getCollection())) {
                        return -1;
                    }
                    if (!empty($b->getCollection())) {
                        return 1;
                    }
                    return 0;
                }
                return strcmp($a->getInstitution()->getName(), $b->getInstitution()->getName());
            }
            if (!empty($a->getInstitution())) {
                return -1;
            }
            if (!empty($b->getInstitution())) {
                return 1;
            }
            return 0;
        }
        return strcmp($a->getRegionWithParents()->getName(), $b->getRegionWithParents()->getName());
    }

    public static function sortByHistoricalName(Location $a, Location $b): int
    {
        if ($a->getRegionWithParents()->getHistoricalName() == $b->getRegionWithParents()->getHistoricalName()) {
            if (!empty($a->getInstitution()) && !empty($b->getInstitution())) {
                if ($a->getInstitution()->getName() == $b->getInstitution()->getName()) {
                    if (!empty($a->getCollection()) && !empty($b->getCollection())) {
                        return strcmp($a->getCollection()->getName(), $b->getCollection()->getName());
                    }
                    if (!empty($a->getCollection())) {
                        return -1;
                    }
                    if (!empty($b->getCollection())) {
                        return 1;
                    }
                    return 0;
                }
                return strcmp($a->getInstitution()->getName(), $b->getInstitution()->getName());
            }
            if (!empty($a->getInstitution())) {
                return -1;
            }
            if (!empty($b->getInstitution())) {
                return 1;
            }
            return 0;
        }
        return strcmp($a->getRegionWithParents()->getHistoricalName(), $b->getRegionWithParents()->getHistoricalName());
    }
}
