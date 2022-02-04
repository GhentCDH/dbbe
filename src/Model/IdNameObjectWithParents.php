<?php

namespace App\Model;

use ReflectionClass;

class IdNameObjectWithParents implements IdJsonInterface
{
    protected $array;

    public function __construct(array $array)
    {
        $this->array = $array;
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): int
    {
        return $this->getLastChild()->getId();
    }

    public function getName(): string
    {
        $names = [];
        foreach ($this->array as $idNameObject) {
            $names[] = $idNameObject->getName();
        }
        return implode(' > ', $names);
    }

    public function getInverseName(): string
    {
        $names = [];
        foreach ($this->array as $idNameObject) {
            $names[] = $idNameObject->getName();
        }
        return implode(' < ', array_reverse($names));
    }

    public function getIndividualName(): ?string
    {
        return $this->getLastChild()->getName();
    }

    public function getParent(): ?IdNameObject
    {
        if (count($this->array) < 2) {
            return null;
        }
        return $this->array[count($this->array) -2];
    }

    public function getArray(): array
    {
        return $this->array;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
        ];
    }

    public function getJson(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'parent' => $this->getParent() ? $this->getParent()->getShortJson() : null,
            'individualName' => $this->getIndividualName(),
        ];
    }

    public function getIndividualJson(): array
    {
        return $this->getLastChild()->getJson();
    }

    public function getIndividualShortJson(): array
    {
        return $this->getLastChild()->getShortJson();
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
                'name' => $object->getName(),
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

    protected function getLastChild(): IdNameObject
    {
        $child = end($this->array);
        reset($this->array);
        return $child;
    }
}
