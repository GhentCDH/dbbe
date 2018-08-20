<?php

namespace AppBundle\Model;

class BookBibliography extends Bibliography
{
    const CACHENAME = 'book_bibliography';

    use RawPagesTrait;
    use StartEndPagesTrait;

    protected $book;

    public function __construct(int $id)
    {
        parent::__construct($id, 'book');
    }

    public function setBook(Book $book): BookBibliography
    {
        $this->book = $book;

        return $this;
    }

    public function getBook(): Book
    {
        return $this->book;
    }

    public function getDescription(): string
    {
        return
            $this->book->getDescription()
            . $this->formatStartEndPages(': ', $this->rawPages)
            . '.';
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'book' => $this->book->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
            'rawPages' => $this->rawPages,
        ];
    }
}
