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

    public function getHistoricalElastic(): string
    {
        $result = [];
        $array = $this->array;
        $last = true;
        while (count($array) > 0) {
            $object = new IdNameObjectWithParents($array);
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
