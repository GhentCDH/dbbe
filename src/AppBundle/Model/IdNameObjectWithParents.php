<?php

namespace AppBundle\Model;

class IdNameObjectWithParents implements IdJsonInterface
{
    use CacheDependenciesTrait;

    protected $array;

    public function __construct(array $array)
    {
        $this->array = $array;
    }

    public function getId(): int
    {
        return $this->getLastChild()->getId();
    }

    public function getName(): string
    {
        $names = [];
        foreach ($this->array as $content) {
            $names[] = $content->getName();
        }
        return implode(' > ', $names);
    }

    public function getIndividualName(): string
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

    public function getShortElastic(): array
    {
        $result = [];
        $array = $this->array;
        $last = true;
        while (count($array) > 0) {
            $object = new IdNameObjectWithParents($array);
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
