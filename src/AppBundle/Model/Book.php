<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Book extends Document
{
    const CACHENAME = 'book';

    use CacheLinkTrait;

    protected $year;
    protected $title;
    protected $city;
    protected $editor;
    protected $publisher;
    protected $series;
    protected $volume;
    protected $totalVolumes;
    protected $translators;

    public function __construct(
        int $id,
        int $year,
        string $title,
        string $city,
        string $editor = null
    ) {
        parent::__construct();

        $this->id = $id;
        $this->year = $year;
        $this->title = $title;
        $this->city = $city;
        $this->editor = $editor;

        // All books are public
        $this->public = true;

        return $this;
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

    public function getEditor(): ?string
    {
        return $this->editor;
    }

    public function setPublisher(string $publisher = null): Book
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setSeries(string $series = null): Book
    {
        $this->series = $series;

        return $this;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setVolume(int $volume = null): Book
    {
        $this->volume = $volume;

        return $this;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function setTotalVolumes(int $totalVolumes = null): Book
    {
        $this->totalVolumes = $totalVolumes;

        return $this;
    }

    public function getTotalVolumes(): ?int
    {
        return $this->totalVolumes;
    }

    public function addTranslator(Person $translator): Book
    {
        $this->translators[] = $translator;

        return $this;
    }

    public function getTranslators(): array
    {
        return $this->translators;
    }

    public function getDescription(): string
    {
        $authorNames = [];
        if (isset($this->personRoles['author'])) {
            foreach ($this->personRoles['author'][1] as $author) {
                $authorNames[] = $author->getShortDescription();
            }
        }
        return
            implode(', ', $authorNames)
            . ' ' . $this->year
            . ', ' . $this->title
            . ', ' . $this->city;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->year)) {
            $result['year'] = $this->year;
        }
        if (!empty($this->city)) {
            $result['city'] = $this->city;
        }
        if (!empty($this->editor)) {
            $result['editor'] = $this->editor;
        }
        if (!empty($this->publisher)) {
            $result['publisher'] = $this->publisher;
        }
        if (!empty($this->series)) {
            $result['series'] = $this->series;
        }
        if (!empty($this->volume)) {
            $result['volume'] = $this->volume;
        }
        if (!empty($this->totalVolumes)) {
            $result['totalVolumes'] = $this->totalVolumes;
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['type'] = [
            'id' => 1,
            'name' => 'Book',
        ];

        $result['title'] = $this->title;
        foreach ($this->getPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        foreach ($this->getPublicPersonRoles() as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }

        return $result;
    }

    public static function unlinkCache($data)
    {
        $book = new Book($data['id'], $data['year'], $data['title'], $data['city']);

        foreach ($data as $key => $value) {
            $book->set($key, $value);
        }

        return $book;
    }
}
