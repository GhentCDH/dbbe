<?php

namespace AppBundle\Model;

class Document
{
    protected $id;

    public function setId(int $id): Manuscript
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
