<?php

namespace App\Model;

use App\Utils\VolumeSortKey;

class BookSeries extends Document
{
    const CACHENAME = 'book_series';

    use UrlsTrait;

    /**
     * @var array
     */
    protected $books = [];

    public function __construct(
        int $id,
        string $title
    ) {
        $this->id = $id;
        $this->title = $title;

        $this->public = true;
    }

    public function setBooks(array $books): BookSeries
    {
        $this->books = $books;

        return $this;
    }

    public function addBook(Book $book): BookSeries
    {
        $this->books[] = $book;

        return $this;
    }

    public function getBooks(): array
    {
        $books = $this->books;

        usort(
            $books,
            function ($a, $b) {
                return strcmp(VolumeSortKey::sortKey($a->getSeriesVolume()), VolumeSortKey::sortKey($b->getSeriesVolume()));
            }
        );

        return $books;
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
            'id' => 6,
            'name' => 'Book series',
        ];

        $result['title'] = $this->title;

        return $result;
    }
}