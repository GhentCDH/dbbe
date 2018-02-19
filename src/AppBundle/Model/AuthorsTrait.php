<?php

namespace AppBundle\Model;

trait AuthorsTrait
{
    private $authors = [];

    public function addAuthor(Person $author)
    {
        $this->authors[] = $author;

        return $this;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }
}
