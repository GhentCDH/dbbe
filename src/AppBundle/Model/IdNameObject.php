<?php

namespace AppBundle\Model;

class IdNameObject implements IdJsonInterface
{
    protected $id;
    protected $name;

    public function __construct(int $id, string $name = null)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function getJson(): array
    {
        return $this->getShortJson();
    }

    public function getElastic(): array
    {
        return $this->getJson();
    }
}
