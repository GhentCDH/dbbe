<?php

namespace AppBundle\Model;

use ReflectionClass;

abstract class Bibliography
{
    /**
     * @var string
     */
    const CACHENAME = 'bibliography';

    /**
     * @var int
     */
    protected $id;
    /**
     * article, book, bookChapter or onlineSource
     * @var string
     */
    protected $type;
    /**
     * @var ReferenceType
     */
    protected $referenceType;
    /**
     * @var string
     */
    protected $image;

    protected function __construct(int $id, string $type)
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

    public function setReferenceType(ReferenceType $referenceType = null): Bibliography
    {
        $this->referenceType = $referenceType;
        return $this;
    }

    public function getReferenceType(): ?ReferenceType
    {
        return $this->referenceType;
    }

    public function setImage(string $image = null): Bibliography
    {
        $this->image = $image;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    abstract public function getDescription(): string;

    abstract public function getShortJson(): array;
}
