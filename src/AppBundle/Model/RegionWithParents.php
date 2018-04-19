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
            'name' => $this->getName(),
            'historicalName' => $this->getHistoricalName(),
            'parent' => $this->getParent() ? $this->getParent()->getShortJson() : null,
            'individualName' => $this->getIndividualName(),
            'individualHistoricalName' => $this->getIndividualHistoricalName(),
            'isCity' => $this->getIsCity(),
            'pleiades' => $this->getPleiades(),
        ];
    }

    public function getHistoricalElastic(bool $display = true): array
    {
        $result = [];
        $array = $this->array;
        while (count($array) > 0) {
            $object = new RegionWithParents($array);
            $entry = [
                'id' => $object->getId(),
                'name' => $object->getHistoricalName(),
            ];
            if ($display) {
                $display = false;
                $entry['display'] = true;
            }
            $result[] = $entry;
            array_pop($array);
        }
        return $result;
    }

    public static function sortByNameHistoricalName(RegionWithParents $a, RegionWithParents $b): int
    {
        if (!empty($a->getName()) && !empty($b->getName())) {
            return strcmp($a->getName(), $b->getName());
        }
        if (!empty($a->getName())) {
            return -1;
        }
        if (!empty($b->getName())) {
            return 1;
        }
        return strcmp($a->getHistoricalName(), $b->getHistoricalName());
    }
}
