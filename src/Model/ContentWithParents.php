<?php

namespace App\Model;

use ReflectionClass;

class ContentWithParents extends IdNameObjectWithParents
{
    const CACHENAME = 'content_with_parents';

    public function getDisplayName(): string
    {
        $names = [];
        foreach ($this->array as $idNameObject) {
            $names[] = $idNameObject->getDisplayName();
        }
        return implode(' > ', $names);
    }

    public function getIndividualPerson(): ?Person
    {
        return $this->getLastChild()->getPerson();
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getDisplayName(),
        ];
    }

    public function getJson(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getDisplayName(),
            'parent' => $this->getParent() ? $this->getParent()->getShortJson() : null,
            'individualName' => $this->getIndividualName(),
            'individualPerson' => $this->getIndividualPerson() ? $this->getIndividualPerson()->getShortJson() : null,
        ];
    }

    public function getShortElastic(): array
    {
        $result = [];
        $array = $this->array;
        $last = true;
        while (count($array) > 0) {
            $object = (new ReflectionClass(static::class))->newInstance($array);
            $entry = [
                'id' => $object->getId(),
                'name' => $object->getDisplayName(),
                'id_name' => $object->getId() . '_' . $object->getDisplayName(),
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
