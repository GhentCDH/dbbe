<?php

namespace AppBundle\Model;

class BookCluster extends Document
{
    const CACHENAME = 'book_cluster';

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
            'id' => 5,
            'name' => 'Book cluster',
        ];

        $result['title'] = $this->title;

        return $result;
    }
}