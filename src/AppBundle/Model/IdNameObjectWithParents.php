<?php

namespace AppBundle\Model;

class IdNameObjectWithParents
{
    use CacheDependencies;

    protected $array;

    public function __construct(array $array)
    {
        $this->array = $array;
    }

    public function getId(): int
    {
        $child = end($this->array);
        reset($this->array);
        return $child->getId();
    }

    public function getName(): string
    {
        $names = [];
        foreach ($this->array as $content) {
            $names[] = $content->getName();
        }
        return implode(' > ', $names);
    }

    public function getArray(): array
    {
        return $this->array;
    }

    public function getElastic(): array
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
}
