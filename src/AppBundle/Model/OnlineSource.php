<?php

namespace AppBundle\Model;

use DateTime;

class OnlineSource
{
    private $id;
    private $baseUrl;
    private $name;
    private $lastAccessed;

    public function __construct(
        int $id,
        string $baseUrl,
        string $name,
        string $lastAccessed
    ) {
        $this->id = $id;
        $this->baseUrl = $baseUrl;
        $this->name = $name;
        $this->lastAccessed = new DateTime($lastAccessed);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLastAccessed(): ?DateTime
    {
        return $this->lastAccessed;
    }
}
