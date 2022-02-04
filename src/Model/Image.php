<?php

namespace App\Model;

use TypeError;

/**
 */
class Image implements IdJsonInterface
{
    /**
     * @var string
     */
    const CACHENAME = 'image';

    /**
     * @var int
     */
    protected $id;
    /**
     * @var string
     */
    protected $filename;
    /**
     * @var string
     */
    protected $url;
    /**
     * @var bool
     */
    protected $public;

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

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'public' => $this->public,
        ];

        if (!empty($this->filename)) {
            $result['filename'] = $this->filename;
        }
        if (!empty($this->url)) {
            $result['url'] = $this->url;
        }

        return $result;
    }
}
