<?php

namespace App\Model;

use URLify;

use App\Utils\ArrayToJson;
use App\Utils\VolumeSortKey;

/**
 */
class Book extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'book';

    use UrlsTrait;

    /**
     * @var BookCluster
     */
    protected $bookCluster;

    /**
     * @var int
     */
    protected $year;
    /**
     * @var bool
     */
    protected $forthcoming;
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
     * @var BookSeries
     */
    protected $series;
    /**
     * @var string
     */
    protected $seriesVolume;
    /**
     * @var string
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
     * @param int              $id
     * @param int|null         $year
     * @param bool             $forthcoming
     * @param string           $city
     * @param string|null      $title
     * @param BookCluster|null $bookCluster
     * @param string|null      $editor
     * @param string|null      $volume
     */
    public function __construct(
        int $id,
        int $year = null,
        bool $forthcoming,
        string $city,
        string $title = null,
        BookCluster $bookCluster = null,
        string $editor = null,
        string $volume = null
    ) {
        $this->id = $id;
        $this->year = $year;
        $this->forthcoming = $forthcoming;
        $this->city = $city;
        $this->title = $title;
        $this->bookCluster = $bookCluster;
        $this->editor = $editor;
        $this->volume = $volume;

        // All books are public
        $this->public = true;

        return $this;
    }

    /**
     * @return int
     */
    public function getYear(): ?int
    {
        return $this->year;
    }

    /**
     * @return bool
     */
    public function getForthcoming(): bool
    {
        return $this->forthcoming;
    }

    /**
     * @return BookCluster|null
     */
    public function getCluster(): ?BookCluster
    {
        return $this->bookCluster;
    }

    /**
     * @return string
     */
    public function getBigTitle(): string
    {
        if ($this->bookCluster != null) {
            return $this->bookCluster->getTitle();
        }
        return $this->title;
    }

    /**
     * @return string|null
     */
    public function getSmallTitle(): ?string
    {
        if ($this->bookCluster != null && $this->title != null) {
            return $this->title;
        }
        return null;
    }

    /**
     * @return string
     */
    public function getTitleSortKey(): string
    {
        if ($this->bookCluster == null) {
            return $this->title;
        }
        return $this->bookCluster->getTitle() . VolumeSortKey::sortKey($this->volume) . $this->title;
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
     * @param BookSeries|null $series
     * @return Book
     */
    public function setSeries(BookSeries $series = null): Book
    {
        $this->series = $series;

        return $this;
    }

    /**
     * @return BookSeries|null
     */
    public function getSeries(): ?BookSeries
    {
        return $this->series;
    }

    /**
     * @param string|null $seriesVolume
     * @return $this
     */
    public function setSeriesVolume(string $seriesVolume = null): Book
    {
        $this->seriesVolume = $seriesVolume;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSeriesVolume(): ?string
    {
        return $this->seriesVolume;
    }

    /**
     * @return string|null
     */
    public function getVolume(): ?string
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
    public function getFullTitleAndVolume(): string
    {
        return (!empty($this->bookCluster) ? $this->bookCluster->getTitle() : '')
            . ((!empty($this->bookCluster) && !empty($this->title)) ? '. ' : '')
            . $this->title
            . (!empty($this->volume) ? ' (vol. ' . $this->volume . ')' : '');
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
            (
                !empty($authorNames)
                    ? implode(', ', $authorNames) . (
                        !empty($editornames)
                            ? (count($authorNames) > 1 ? ' (auths.)' :  ' (auth.)') . '; '
                            : ', '
                    )
                    : ''
            )
            . (
                !empty($editornames)
                    ? implode(', ', $editornames) . (count($editornames) > 1 ? ' (eds.), ' :  ' (ed.), ')
                    : ''
            )
            . ' '
            . (
                $this->forthcoming
                    ? '(forthcoming)'
                    : $this->year
            )
            . ', ' . $this->getFullTitleAndVolume()
            . ', ' . $this->city;
    }

    /**
     * Generate a sortKey; see Entity -> getBibliographiesForDisplay()
     *
     * @return string
     */
    public function getSortKey(): string
    {
        $sortKey = 'a';

        if (!empty($this->personRoles['author'])) {
            $lastName = reset($this->personRoles['author'][1])->getLastName();
            if (!empty($lastName)) {
                $sortKey .= URLify::filter($lastName);
            } else {
                $sortKey .= 'zzz';
            }
        } else {
            $sortKey .= 'zzz';
        }

        $year = $this->getYear();
        if (!empty($year)) {
            $sortKey .= $year;
        } else {
            $sortKey .= '9999';
        }

        $volume = $this->getVolume();
        if (!empty($volume)) {
            $sortKey .= VolumeSortKey::sortKey($volume);
        } else {
            $sortKey .= '99999999';
        }

        return $sortKey;
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

        if (!empty($this->bookCluster)) {
            $result['bookCluster'] = $this->bookCluster->getShortJson();
        }
        if (!empty($this->volume)) {
            $result['volume'] = $this->volume;
        }
        if (!empty($this->totalVolumes)) {
            $result['totalVolumes'] = $this->totalVolumes;
        }
        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->year)) {
            $result['year'] = $this->year;
        }
        $result['forthcoming'] = $this->forthcoming;
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
            $result['bookSeries'] = $this->series->getShortJson();
        }
        if (!empty($this->seriesVolume)) {
            $result['seriesVolume'] = $this->seriesVolume;
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

        $result['title'] = $this->getFullTitleAndVolume();
        $personRoles = $this->getPersonRoles();
        foreach ($personRoles as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($personRoles['author']) && count($personRoles['author'][1]) > 0) {
            $result['author_last_name'] = reset($personRoles['author'][1])->getLastName();
        }
        $publicPersonRoles = $this->getPublicPersonRoles();
        foreach ($publicPersonRoles as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($publicPersonRoles['author']) && count($publicPersonRoles['author'][1]) > 0) {
            $result['author_last_name_public'] = reset($publicPersonRoles['author'][1])->getLastName();
        }

        return $result;
    }
}
