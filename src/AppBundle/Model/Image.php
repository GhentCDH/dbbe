<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Image
{
    private $id;
    private $url;
    private $public;

    public function __construct(int $id, string $url, bool $public)
    {
        $this->id = $id;
        $this->url = $url;
        $this->public = $public;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPublic(): bool
    {
        return $this->public;
    }
}
