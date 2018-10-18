<?php

namespace AppBundle\Model;

use stdClass;

class LocatedAt implements IdJsonInterface
{
    const CACHENAME = 'located_at';

    /**
     * Location id is actually the document id, since it is the unique column in this table
     * @var int
     */
    private $id;
    private $location;
    private $shelf;
    private $extra;

    public function __construct()
    {
        return $this;
    }

    public function setId(int $id): LocatedAt
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setLocation(Location $location): LocatedAt
    {
        $this->location = $location;

        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setShelf(string $shelf): LocatedAt
    {
        $this->shelf = $shelf;

        return $this;
    }

    public function getShelf(): string
    {
        return $this->shelf;
    }

    public function setExtra(string $extra = null): LocatedAt
    {
        $this->extra = $extra;

        return $this;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function getName(): string
    {
        $name = strtoupper($this->location->getRegionWithParents()->getIndividualName());
        $name .= ' - ' . $this->location->getInstitution()->getName();
        if (!empty($this->location->getCollection())) {
            $name .= ' - ' . $this->location->getCollection()->getName();
        }
        $name .= ' ' . $this->shelf;
        if (!empty($this->extra)) {
            $name .= ' (' . $this->extra . ')';
        }

        return $name;
    }

    public function getJson(): array
    {
        return [
            'id' => $this->id,
            'location' => $this->location->getJson(),
            'shelf' => $this->shelf,
            'extra' => $this->extra,
        ];
    }
}
