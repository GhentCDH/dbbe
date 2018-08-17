<?php

namespace AppBundle\Model;

class Region extends IdNameObject
{
    private $historicalName;
    private $isCity;
    private $pleiades;

    public function __construct(
        int $id,
        string $name = null,
        string $historicalName = null,
        bool $isCity = null,
        int $pleiades = null
    ) {
        parent::__construct($id, $name);
        $this->historicalName = $historicalName;
        $this->isCity = $isCity;
        $this->pleiades = $pleiades;
        return $this;
    }

    public function getHistoricalName(): ?string
    {
        return $this->historicalName;
    }

    public function getIsCity(): ?bool
    {
        return $this->isCity;
    }

    public function getPleiades(): ?int
    {
        return $this->pleiades;
    }

    public function getJson(): array
    {
        $result = parent::getJson();
        $result['historicalName'] = $this->historicalName;
        $result['isCity'] = $this->isCity;
        $result['pleiades'] = $this->pleiades;
        return $result;
    }
}
