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

    protected $id;
    protected $type;
    protected $refType;

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

    public static function unlinkCache(array $data)
    {
        $object = (new ReflectionClass(static::class))->newInstance($data['id']);

        foreach ($data as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
    }
}
