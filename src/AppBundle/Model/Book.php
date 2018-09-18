<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

/**
 */
class Book extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'book';

    use CacheLinkTrait;

    /**
     * @var int
     */
    protected $year;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var string
     */
    protected $city;
    /**
     * @var string
     */
    protected $editor;
    /**
     * @var string
     */
    protected $publisher;
    /**
     * @var string
     */
    protected $series;
    /**
     * @var int
     */
    protected $volume;
    /**
     * @var int
     */
    protected $totalVolumes;

    /**
     * @param int         $id
     * @param int         $year
     * @param string      $title
     * @param string      $city
     * @param string|null $editor
     */
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
        $this->editor = $editor;

        // All books are public
        $this->public = true;

        return $this;
    }

    /**
     * @return int
     */
    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @return string|null
     */
    public function getEditor(): ?string
    {
        return $this->editor;
    }

    /**
     * @param string|null $publisher
     * @return Book
     */
    public function setPublisher(string $publisher = null): Book
    {
        $this->publisher = $publisher;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    /**
     * @param string|null $series
     * @return Book
     */
    public function setSeries(string $series = null): Book
    {
        $this->series = $series;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSeries(): ?string
    {
        return $this->series;
    }

    /**
     * @param  int|null $volume
     * @return Book
     */
    public function setVolume(int $volume = null): Book
    {
        $this->volume = $volume;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getVolume(): ?int
    {
        return $this->volume;
    }

    /**
     * @param  int|null $totalVolumes
     * @return Book
     */
    public function setTotalVolumes(int $totalVolumes = null): Book
    {
        $this->totalVolumes = $totalVolumes;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTotalVolumes(): ?int
    {
        return $this->totalVolumes;
    }

    /**
     * @return string
     */
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

    /**
     * @return array
     */
    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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

    /**
     * @param array $data
     * @return Book
     */
    public static function unlinkCache(array $data)
    {
        $book = new Book($data['id'], $data['year'], $data['title'], $data['city']);

        foreach ($data as $key => $value) {
            $book->set($key, $value);
        }

        return $book;
    }
}
