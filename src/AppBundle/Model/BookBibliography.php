<?php

namespace AppBundle\Model;

class BookBibliography extends Bibliography
{
    use StartEndPagesTrait;

    private $book;

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
            . $this->formatStartEndPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        return [
            'type' => $this->type,
            'book' => $this->book->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
        ];
    }
}
