<?php

namespace AppBundle\Model;

abstract class Bibliography
{
    use CacheDependenciesTrait;

    protected $id;
    protected $type;
    protected $refType;

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

    public function setRefType(string $refType = null): Bibliography
    {
        $this->refType = $refType;
        return $this;
    }

    public function getRefType(): ?string
    {
        return $this->refType;
    }

    abstract public function getDescription(): string;

    abstract public function getShortJson(): array;
}
