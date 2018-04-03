<?php

namespace AppBundle\Model;

class Region extends IdNameObject implements IdJsonInterface
{
    private $historicalName;

    public function __construct(int $id, string $name, string $historicalName = null)
    {
        parent::__construct($id, $name);
        $this->historicalName = $historicalName;
    }

    public function getHistoricalName(): ?string
    {
        return $this->historicalName;
    }

    public function getJson(): array
    {
        $result = parent::getJson();
        $result['historical_name'] = $this->historicalName;
        return $result;
    }
}
