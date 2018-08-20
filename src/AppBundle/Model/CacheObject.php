<?php

namespace AppBundle\Model;

class CacheObject
{
    private $className;
    private $data;

    public function __construct(string $className, array $data)
    {
        $this->className = $className;
        $this->data = $data;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
