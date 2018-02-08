<?php

namespace AppBundle\Model;

class Article
{
    use AuthorsTrait;
    use StartEndPagesTrait;

    private $id;
    private $title;
    private $journal;

    public function __construct(
        int $id,
        string $title,
        Journal $journal
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->journal = $journal;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getJournal(): Journal
    {
        return $this->journal;
    }
}
