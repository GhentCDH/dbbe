<?php

namespace App\Model;

use URLify;

use App\Utils\ArrayToJson;
use App\Utils\VolumeSortKey;

/**
 */
class Phd extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'phd';

    use UrlsTrait;

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
    protected $institution;
    /**
     * @var string
     */
    protected $volume;

    /**
     * @param int          $id
     * @param int|null     $year
     * @param bool         $forthcoming
     * @param string       $city
     * @param string       $title
     * @param string|null  $institution
     * @param string|null  $volume
     */
    public function __construct(
        int $id,
        int $year = null,
        bool $forthcoming,
        string $city,
        string $title,
        string $institution = null,
        string $volume = null
    ) {
        $this->id = $id;
        $this->year = $year;
        $this->forthcoming = $forthcoming;
        $this->city = $city;
        $this->title = $title;
        $this->institution = $institution;
        $this->volume = $volume;

        // All phds are public
        $this->public = true;

        return $this;
    }

    /**
     * @return int|null
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
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @return string|null
     */
    public function getInstitution(): ?string
    {
        return $this->institution;
    }

    /**
     * @return string|null
     */
    public function getVolume(): ?string
    {
        return $this->volume;
    }

    /**
     * @return string
     */
    public function getFullTitleAndVolume(): string
    {
        return $this->title
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
        return
            implode(', ', $authorNames)
            . ' '
            . (
                $this->forthcoming
                    ? '(forthcoming)'
                    : $this->year
            )
            . ', ' . $this->getFullTitleAndVolume()
            . (!empty($this->getCity()) ? ', ' . $this->getCity() : '');
    }

    /**
     * Generate a sortKey; see Entity -> getBibliographiesForDisplay()
     *
     * @return string
     */
    public function getSortKey(): string
    {
        $sortKey = 'a';

        $lastName = reset($this->personRoles['author'][1])->getLastName();
        if (!empty($lastName)) {
            $sortKey .= URLify::filter($lastName);
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

        if (!empty($this->year)) {
            $result['year'] = $this->year;
        }
        $result['forthcoming'] = $this->forthcoming;
        if (!empty($this->city)) {
            $result['city'] = $this->city;
        }
        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->institution)) {
            $result['volume'] = $this->institution;
        }
        if (!empty($this->volume)) {
            $result['volume'] = $this->volume;
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
            'id' => 9,
            'name' => 'Phd thesis',
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
