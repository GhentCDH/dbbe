<?php

namespace AppBundle\Model;

class Institution extends IdNameObject
{
    public function getHistoricalName(): string
    {
        return $this->name;
    }
}
