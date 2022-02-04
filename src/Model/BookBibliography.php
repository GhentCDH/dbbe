<?php

namespace App\Model;

class BookBibliography extends Bibliography
{
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
            . $this->formatPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'book' => $this->book->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
            'rawPages' => $this->rawPages,
        ];

        if (isset($this->referenceType)) {
            $result['referenceType'] = $this->referenceType->getShortJson();
        }
        if (isset($this->image)) {
            $result['image'] = $this->image;
        }

        return $result;
    }
}
