<?php

namespace App\Model;

class Url implements IdJsonInterface
{
    /**
     * @var int
     */
    protected $id;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var string
     */
    protected $url;

    /**
     * Url constructor.
     * @param int $id
     * @param string $url
     * @param string|null $title
     */
    public function __construct(
        int $id,
        string $url,
        string $title = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->url = $url;

        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'url' => $this->url,
        ];

        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }

        return $result;
    }

    public function getJson(): array
    {
        return $this->getShortJson();
    }
}