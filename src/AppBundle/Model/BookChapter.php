<?php

namespace AppBundle\Model;

class BookChapter
{
    use AuthorsTrait;
    use CacheDependenciesTrait;
    use StartEndPagesTrait;

    private $id;
    private $title;
    private $book;

    public function __construct(
        int $id,
        string $title,
        Book $book
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->book = $book;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBook(): Book
    {
        return $this->book;
    }

    public function getDescription(): string
    {
        $authorNames = [];
        foreach ($this->authors as $author) {
            $authorNames[] = $author->getShortDescription();
        }
        return
            implode(', ', $authorNames)
            . ' ' . $this->book->getYear()
            . ', ' . $this->getTitle()
            . ', in '
            . (
                !empty($this->book->getEditor())
                    ? $this->book->getEditor() . ' (ed.) '
                    : ''
            )
            . ', ' . $this->book->getTitle()
            . ', ' . $this->book->getCity()
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
