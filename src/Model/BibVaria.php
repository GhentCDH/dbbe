<?php

namespace App\Model;

use URLify;

use App\Utils\ArrayToJson;

/**
 */
class BibVaria extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'bib_varia';

    use UrlsTrait;

    /**
     * @param int $id
     * @param string $title
     * @param int|null $year
     * @param string|null $city
     * @param string|null $institution
     */
    public function __construct(
        int $id,
        string $title,
        int $year = null,
        string $city = null,
        string $institution = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->year = $year;
        $this->city = $city;
        $this->institution = $institution;

        // All bib varias are public
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
     * @return string|null
     */
    public function getCity(): ?string
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
            . (!empty($this->getYear()) ? ' ' . $this->getYear() : '')
            . ', ' . $this->getTitle()
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

        if (!empty($this->year)) {
            $sortKey .= $this->year;
        } else {
            $sortKey .= '9999';
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
        if (!empty($this->city)) {
            $result['city'] = $this->city;
        }
        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->institution)) {
            $result['institution'] = $this->institution;
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
            'id' => 10,
            'name' => 'Varia',
            'id_name' => 10 . '_' . 'Varia',
        ];

        $result['title'] = $this->title;
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
