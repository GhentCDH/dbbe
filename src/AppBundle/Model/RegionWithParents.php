<?php

namespace AppBundle\Model;

class RegionWithParents extends IdNameObjectWithParents
{
    public function getHistoricalName(): string
    {
        $names = [];
        foreach ($this->array as $content) {
            $names[] = $content->getHistoricalName();
        }
        return implode(' > ', $names);
    }

    public function getIndividualHistoricalName(): string
    {
        return $this->getLastChild()->getHistoricalName();
    }

    public function getIsCity(): bool
    {
        return $this->getLastChild()->getIsCity();
    }

    public function getPleiades(): ?int
    {
        return $this->getLastChild()->getPleiades();
    }

    public function getJson(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName() ? $this->getName() : '[' . $this->getHistoricalName() . ']',
            'historicalName' => $this->getHistoricalName(),
            'parent' => $this->getParent() ? $this->getParent()->getShortJson() : null,
            'individualName' => $this->getIndividualName(),
            'individualHistoricalName' => $this->getIndividualHistoricalName(),
            'isCity' => $this->getIsCity(),
            'pleiades' => $this->getPleiades(),
        ];
    }

    public function getHistoricalElastic(): array
    {
        $result = [];
        $array = $this->array;
        $last = true;
        while (count($array) > 0) {
            $object = new RegionWithParents($array);
            $entry = [
                'id' => $object->getId(),
                'name' => $object->getHistoricalName(),
            ];
            if ($last) {
                $last = false;
                $entry['display'] = true;
            }
            $result[] = $entry;
            array_pop($array);
        }
        return $result;
    }
}
