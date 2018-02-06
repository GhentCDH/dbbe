<?php

namespace AppBundle\Model;

class Origin
{
    private $id;
    private $institution;
    private $regionWithParents;

    public function __construct()
    {
        return $this;
    }

    public function setId(int $id): Origin
    {
        $this->id = $id;

        return $this;
    }

    public function setInstitution(Institution $institution): Origin
    {
        $this->institution = $institution;

        return $this;
    }

    public function setRegionWithParents(RegionWithParents $regionWithParents): Origin
    {
        $this->regionWithParents = $regionWithParents;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    private function getFullRegion(): RegionWithParents
    {
        $array = $this->regionWithParents->getArray();
        if (isset($this->institution)) {
            $array = array_merge($array, [$this->institution]);
        }
        return new RegionWithParents($array);
    }

    public function getName(): string
    {
        return $this->getFullRegion()->getHistoricalName();
    }

    public function getElastic(): array
    {
        return $this->getFullRegion()->getHistoricalElastic();
    }
}
