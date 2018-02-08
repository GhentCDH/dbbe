<?php

namespace AppBundle\Model;

class Book
{
    use AuthorsTrait;

    private $id;
    private $year;
    private $title;
    private $city;
    private $editor;

    public function __construct(
        int $id,
        int $year,
        string $title,
        string $city,
        string $editor = null
    ) {
        $this->id = $id;
        $this->year = $year;
        $this->title = $title;
        $this->city = $city;
        $this->city = $city;
        $this->editor = $editor;

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

    public function getCity(): string
    {
        return $this->city;
    }

    public function getEditor()
    {
        return $this->editor;
    }
}
