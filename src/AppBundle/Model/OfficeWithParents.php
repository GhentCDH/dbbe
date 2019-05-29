<?php

namespace AppBundle\Model;

class OfficeWithParents extends IdNameObjectWithParents
{
    const CACHENAME = 'office_with_parents';

    public function getIndividualRegionWithParents() : ?RegionWithParents
    {
        return $this->getLastChild()->getRegionWithParents();
    }

    public function getName(): string
    {
        $names = [];
        foreach ($this->array as $office) {
            $names[] = $office->getName() ? $office->getName() : 'of ' . $office->getRegionWithParents()->getInverseHistoricalName();
        }
        $name = implode(' > ', $names);
        return str_replace(' > of ', ' of ', $name);
    }

    public function getJson(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'parent' => $this->getParent() ? $this->getParent()->getShortJson() : null,
            'individualName' => $this->getIndividualName(),
            'individualRegionWithParents' => $this->getIndividualRegionWithParents() ? $this->getIndividualRegionWithParents()->getShortHistoricalJson() : null,
        ];
    }
}
