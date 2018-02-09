<?php

namespace AppBundle\Model;

class Location
{
    use CacheDependenciesTrait;

    /**
     * Location id is actually the document id, since it is the unique column in this table
     * @var int
     */
    private $id;
    private $city;
    private $library;
    private $collection;
    private $shelf;

    public function __construct()
    {
        return $this;
    }

    public function setId(int $id): Location
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setCity(Region $city): Location
    {
        $this->city = $city;

        return $this;
    }

    public function getCity(): Region
    {
        return $this->city;
    }

    public function setLibrary(Library $library): Location
    {
        $this->library = $library;

        return $this;
    }

    public function getLibrary(): Library
    {
        return $this->library;
    }

    public function setCollection(Collection $collection = null): Location
    {
        $this->collection = $collection;

        return $this;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function setShelf(string $shelf): Location
    {
        $this->shelf = $shelf;

        return $this;
    }

    public function getShelf(): string
    {
        return $this->shelf;
    }

    public function getName(): string
    {
        $name = strtoupper($this->city->getName());
        $name .= ' - ' . $this->library->getName();
        if (!empty($this->collection)) {
            $name .= ' - ' . $this->collection->getName();
        }
        $name .= ' ' . $this->shelf;

        return $name;
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'city' => $this->city->getJson(),
            'library' => $this->library->getJson(),
            'shelf' => $this->shelf,
        ];

        if (!empty($this->collection)) {
            $result['collection'] = $this->collection->getJson();
        }

        return $result;
    }

    public static function sortByName(Location $a, Location $b): int
    {
        if ($a->getCity()->getName() === $b->getCity()->getName()) {
            if ($a->getLibrary()->getName() === $b->getLibrary()->getName()) {
                if (!empty($a->getCollection()) && !empty($b->getCollection())) {
                    return strcmp($a->getCollection()->getName(), $b->getCollection()->getName());
                }
                if (!empty($a->getCollection())) {
                    return -1;
                }
                if (!empty($b->getCollection())) {
                    return 1;
                }
                return 0;
            }
            return strcmp($a->getLibrary()->getName(), $b->getLibrary()->getName());
        }
        return strcmp($a->getCity()->getName(), $b->getCity()->getName());
    }
}
