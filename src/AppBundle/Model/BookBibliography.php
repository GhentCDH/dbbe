<?php

namespace AppBundle\Model;

class BookBibliography extends Bibliography
{
    use StartEndPages;

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
        $authorNames = [];
        foreach ($this->book->getAuthors() as $author) {
            $authorNames[] = $author->getShortDescription();
        }
        return implode(', ', $authorNames)
            . ' ' . $this->book->getYear()
            . ', ' . $this->book->getTitle()
            . ', ' . $this->book->getCity()
            . self::formatPages($this->startPage, $this->endPage, ': ')
            . '.';
    }
}
