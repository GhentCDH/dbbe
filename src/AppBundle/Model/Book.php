<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;
use AppBundle\Utils\RomanFormatter;

/**
 */
class Book extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'book';

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
     * @var array
     */
    protected $chapters = [];

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
        string $editor = null,
        int $volume = null
    ) {
        $this->id = $id;
        $this->year = $year;
        $this->title = $title;
        $this->city = $city;
        $this->editor = $editor;
        $this->volume = $volume;

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

    public function addChapter(BookChapter $bookChapter): Book
    {
        $this->chapters[$bookChapter->getId()] = $bookChapter;

        return $this;
    }

    public function sortChapters(): Book
    {
        usort(
            $this->chapters,
            function($a, $b) {
                if (empty($a->getStartPage()) && empty($b->getStartPage())) {
                    return 0;
                } elseif (!empty($a->getStartPage()) && empty($b->getStartPage())) {
                    return -1;
                } elseif (empty($a->getStartPage()) && !empty($b->getStartPage())) {
                    return 1;
                } else {
                    return $a->getStartPage() - $b->getStartPage();
                }
            }
        );

        return $this;
    }

    public function getChapters(): array
    {
        return $this->chapters;
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
        $editornames = [];
        if (isset($this->personRoles['editor'])) {
            foreach ($this->personRoles['editor'][1] as $editor) {
                $editornames[] = $editor->getShortDescription();
            }
        }
        return
            implode(', ', $authorNames)
            . (!empty($authorNames) && !empty($editornames)) {
                '; '
            }
            . (
                !empty($editornames)
                    ? implode(', ', $editornames) . (count($editornames) > 1 ? ' (eds.), ' :  ' (ed.), ')
                    : ''
            )
            . ' ' . $this->year
            . ', ' . $this->title
            . (!empty($this->volume) ? ' ' . RomanFormatter::numberToRoman($this->volume) : '')
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

        $result['title'] = $this->title . (!empty($this->volume) ? ' ' . RomanFormatter::numberToRoman($this->volume) : '');
        foreach ($this->getPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        foreach ($this->getPublicPersonRoles() as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }

        return $result;
    }
}
