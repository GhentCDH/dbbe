<?php

namespace AppBundle\Model;

use TypeError;

class Image
{
    private $id;
    private $filename;
    private $url;
    private $public;

    public function __construct(int $id, string $filename = null, string $url = null, bool $public)
    {
        if ((empty($filename) && empty($url)) || (!empty($filename) && !empty($url))) {
            throw new TypeError('Either url or filename must be provided.');
        }
        $this->id = $id;
        $this->filename = $filename;
        $this->url = $url;
        $this->public = $public;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->filename;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getPublic(): bool
    {
        return $this->public;
    }
}
