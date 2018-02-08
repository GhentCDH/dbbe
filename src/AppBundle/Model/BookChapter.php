<?php

namespace AppBundle\Model;

class BookChapter
{
    use AuthorsTrait;
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
}
