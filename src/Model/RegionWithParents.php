<?php

namespace App\Model;

class RegionWithParents extends IdNameObjectWithParents
{
    const CACHENAME = 'region_with_parents';

    public function getHistoricalName(): string
    {
        $names = [];
        foreach ($this->array as $content) {
            $names[] = $content->getHistoricalName();
        }
        // do not display empty names
        return implode(' > ', array_filter($names));
    }

    public function getInverseHistoricalName(): string
    {
        $names = [];
        foreach ($this->array as $content) {
            $names[] = $content->getHistoricalName();
        }
        // do not display empty names
        return implode(' < ', array_reverse(array_filter($names)));
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

    public function getNameHistoricalName(): string
    {
        return !empty($this->getName()) ? $this->getName() : $this->getHistoricalName();
    }

    public function getPublic(): bool
    {
        // regions (also with parents) are allways public
        return true;
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

    public function getShortHistoricalJson(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getHistoricalName(),
        ];
    }

    public function getHistoricalElastic(bool $display = true): array
    {
        $result = [];
        $array = $this->array;
        while (count($array) > 0) {
            $object = new RegionWithParents($array);
            // Do not add region parts that don't have a historical name
            if (empty($object->getIndividualHistoricalName())) {
                array_pop($array);
                continue;
            }
            $entry = [
                'id' => $object->getId(),
                'name' => $object->getInverseHistoricalName(),
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
}
