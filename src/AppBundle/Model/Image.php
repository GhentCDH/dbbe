<?php

namespace AppBundle\Model;

use TypeError;

/**
 */
class Image implements IdJsonInterface
{
    /**
     * @var string
     */
    const CACHENAME = 'image';

    use CacheLinkTrait;

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

    /**
     * @param array $data
     * @return Book
     */
    public static function unlinkCache(array $data)
    {
        $image = new Image($data['id'], $data['filename'], $data['url'], $data['public']);

        foreach ($data as $key => $value) {
            $image->set($key, $value);
        }

        return $image;
    }
}
