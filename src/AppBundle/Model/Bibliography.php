<?php

namespace AppBundle\Model;

abstract class Bibliography
{
    use CacheDependenciesTrait;

    protected $id;
    protected $type;

    public function __construct(int $id, string $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    abstract public function getDescription(): string;

    abstract public function getShortJson(): array;
}
