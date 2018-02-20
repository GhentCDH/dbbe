<?php

namespace AppBundle\Model;

class Journal
{
    private $id;
    private $title;
    private $year;
    private $volume;
    private $number;

    public function __construct(
        int $id,
        string $title,
        int $year,
        int $volume = null,
        int $number = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->year = $year;
        $this->volume = $volume;
        $this->number = $number;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }
}
