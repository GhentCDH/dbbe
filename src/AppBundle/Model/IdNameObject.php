<?php

namespace AppBundle\Model;

class IdNameObject
{
    protected $id;
    protected $name;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function getElastic(): array
    {
        return $this->getJson();
    }
}
