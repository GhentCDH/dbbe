<?php

namespace AppBundle\Model;

class Origin extends Location
{
    const CACHENAME = 'origin';

    public function getName(): string
    {
        $names = [$this->regionWithParents->getInverseHistoricalName()];
        if (isset($this->institution)) {
            $names[] = $this->institution->getName();
        }
        return implode(' < ', array_reverse($names));
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getName(),
        ];
    }

    public function getShortElastic(): array
    {
        // add all parent regions as well
        // use the ids of the regions / institution
        $array = $this->regionWithParents->getArray();
        if (isset($this->institution)) {
            $result = $this->regionWithParents->getHistoricalElastic(false);
            $result[] = [
                'id' => $this->institution->getId(),
                'name' => $this->getName(),
                'display' => true,
            ];
        } else {
            $result = $this->regionWithParents->getHistoricalElastic();
        }
        return $result;
    }

    public static function fromLocation(Location $location)
    {
        $origin = new Origin;
        $origin->setId($location->getId());
        $origin->setRegionWithParents($location->getRegionWithParents());
        if ($location->getInstitution() != null) {
            $origin->setInstitution($location->getInstitution());
        }
        return $origin;
    }
}
