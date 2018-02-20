<?php

namespace AppBundle\Model;

class Article
{
    use AuthorsTrait;
    use CacheDependenciesTrait;
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

    public function getDescription(): string
    {
        $authorNames = [];
        foreach ($this->authors as $author) {
            $authorNames[] = $author->getShortDescription();
        }
        return
            implode(', ', $authorNames)
            . ' ' . $this->journal->getYear()
            . ', ' . $this->title
            . ', ' . $this->journal->getTitle()
            . (
                !empty($this->journal->getVolume())
                    ? ', ' . $this->journal->getVolume()
                    : ''
            )
            . (
                !empty($this->journal->getNumber())
                    ? '(' . $this->journal->getNumber() . ')'
                    : ''
            )
            . $this->formatStartEndPages(', ');
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }
}
