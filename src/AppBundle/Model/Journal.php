<?php

namespace AppBundle\Model;

class Journal extends Document
{
    const CACHENAME = 'journal';

    use UrlsTrait;

    protected $title;

    public function __construct(
        int $id,
        string $title
    ) {
        $this->id = $id;
        $this->title = $title;

        $this->public = true;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->title,
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        $result['title'] = $this->title;
        $result['name'] = $this->title;

        return $result;
    }

    /**
     * @return array
     */
    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['type'] = [
            'id' => 4,
            'name' => 'Journal',
        ];

        $result['title'] = $this->title;

        return $result;
    }
}