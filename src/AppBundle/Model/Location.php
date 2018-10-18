<?php

namespace AppBundle\Model;

use stdClass;

class Location
{
    const CACHENAME = 'location';

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

        return $this;
    }

    public function getRegionWithParents(): RegionWithParents
    {
        return $this->regionWithParents;
    }

    public function setInstitution(Institution $institution): Location
    {
        $this->institution = $institution;

        return $this;
    }

    public function getInstitution(): ?Institution
    {
        return $this->institution;
    }

    public function setCollection(Collection $collection): Location
    {
        $this->collection = $collection;

        return $this;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function getName(): string
    {
        $names = [$this->regionWithParents->getName()];
        if (isset($this->institution)) {
            $names[] = $this->institution->getName();
        }
        if (isset($this->collection)) {
            $names[] = $this->collection->getName();
        }
        return implode(' > ', $names);
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
}
