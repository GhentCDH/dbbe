<?php

namespace AppBundle\Model;

use ReflectionClass;

abstract class Bibliography
{
    /**
     * @var string
     */
    const CACHENAME = 'bibliography';

    use CacheLinkTrait;
    use CacheObjectTrait;

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
    protected $sourceRemark;
    /**
     * @var string
     */
    protected $note;

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

    public function setSourceremark(string $sourceRemark = null): Bibliography
    {
        $this->sourceRemark = $sourceRemark;
        return $this;
    }

    public function getSourceRemark(): ?string
    {
        return $this->sourceRemark;
    }

    public function setNote(string $note = null): Bibliography
    {
        $this->note = $note;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    abstract public function getDescription(): string;

    abstract public function getShortJson(): array;

    public static function unlinkCache(array $data)
    {
        $object = (new ReflectionClass(static::class))->newInstance($data['id']);

        foreach ($data as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
    }
}
