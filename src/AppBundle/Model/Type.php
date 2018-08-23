<?php

namespace AppBundle\Model;

class Type extends Document
{
    const CACHENAME = 'type';

    protected $incipit;

    public function setIncipit(string $incipit): Type
    {
        $this->incipit = $incipit;

        return $this;
    }

    public function getIncipit(): string
    {
        return $this->incipit;
    }

    public function getDescription(): string
    {
        return $this->incipit;
    }
}
