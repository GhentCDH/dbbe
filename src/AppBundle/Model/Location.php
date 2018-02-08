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
}
