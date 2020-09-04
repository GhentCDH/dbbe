<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;
use AppBundle\Utils\VolumeSortKey;

class BookCluster extends Document
{
    const CACHENAME = 'book_cluster';

    use UrlsTrait;

    /**
     * @var string
     */
    protected $title;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setBooks(array $books): BookCluster
    {
        $this->books = $books;

        return $this;
    }

    public function addBook(Book $book): BookCluster
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
                return strcmp(VolumeSortKey::sortKey($a->getVolume()), VolumeSortKey::sortKey($b->getVolume()));
            }
        );

        return $books;
    }

    public function getAuthors(): array
    {
        $authors = [];
        $authorIds = [];

        foreach ($this->books as $book) {
            $personRoles = $book->getPersonRoles();
            if (isset($personRoles['author']) && count($personRoles['author'][1]) > 0) {
                foreach ($personRoles['author'][1] as $author) {
                    if (!in_array($author->getId(), $authorIds)) {
                        $authors[] = $author;
                        $authorIds[] = $author->getId();
                    }
                }
            }
        }

        return $authors;
    }

    public function getPublicAuthors(): array
    {
        $authors = [];
        $authorIds = [];

        foreach ($this->books as $book) {
            if (!$book->getPublic()) {
                continue;
            }
            $personRoles = $book->getPersonRoles();
            if (isset($personRoles['author']) && count($personRoles['author'][1]) > 0) {
                foreach ($personRoles['author'][1] as $author) {
                    if (!$author->getPublic() || in_array($author->getId(), $authorIds)) {
                        continue;
                    }
                    $authors[] = $author;
                    $authorIds[] = $author->getId();
                }
            }
        }

        return $authors;
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

        $result['author'] = ArrayToJson::arrayToShortJson($this->getAuthors());
        if (count($this->getAuthors()) > 0) {
            $result['author_last_name'] = $this->getAuthors()[0]->getLastName();
        }

        $result['author_public'] = ArrayToJson::arrayToShortJson($this->getPublicAuthors());
        if (count($this->getPublicAuthors()) > 0) {
            $result['author_last_name_public'] = $this->getPublicAuthors()[0]->getLastName();
        }

        return $result;
    }
}