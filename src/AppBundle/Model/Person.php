<?php

namespace AppBundle\Model;

class Person implements SubjectInterface
{
    use CacheDependenciesTrait;

    private $id;
    private $firstName;
    private $lastName;
    private $extra;
    private $unprocessed;
    private $bornDate;
    private $deathDate;
    private $RGK;
    private $VGK;
    private $PBW;
    private $occupations;

    public function __construct()
    {
        return $this;
    }

    public function setId(int $id): Person
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setFirstName(string $firstName = null): Person
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function setLastName(string $lastName = null): Person
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function setExtra(string $extra = null): Person
    {
        $this->extra = $extra;

        return $this;
    }

    public function setUnprocessed(string $unprocessed = null): Person
    {
        $this->unprocessed = $unprocessed;

        return $this;
    }

    public function setBornDate(FuzzyDate $bornDate = null): Person
    {
        $this->bornDate = $bornDate;

        return $this;
    }

    public function setDeathDate(FuzzyDate $deathDate = null): Person
    {
        $this->deathDate = $deathDate;

        return $this;
    }

    public function setRGK(string $volume, string $rgk): Person
    {
        $this->RGK = $volume . '.' . $rgk;

        return $this;
    }

    public function setVGK(string $vgk): Person
    {
        $this->VGK = $vgk;

        return $this;
    }

    public function setPBW(string $pbw): Person
    {
        $this->PBW = $pbw;

        return $this;
    }

    public function setOccupations(array $occupations): Person
    {
        sort($occupations);
        $this->occupations = $occupations;

        return $this;
    }

    public function getFullDescription(): string
    {
        $nameArray = array_filter([
            $this->firstName,
            $this->lastName,
            $this->extra,
        ]);
        if (empty($nameArray)) {
            $description = $this->unprocessed;
        } else {
            $description = implode(' ', $nameArray);
            if (!$this->bornDate->isEmpty() && !$this->deathDate->isEmpty()) {
                $description .= ' (' . new FuzzyInterval($this->bornDate, $this->deathDate) . ')';
            }
        }
        if (isset($this->RGK)) {
            $description .= ' - RGK: ' . $this->RGK;
        }
        if (isset($this->VGK)) {
            $description .= ' - VGK: ' . $this->VGK;
        }
        if (isset($this->PBW)) {
            $description .= ' - PBW: ' . $this->PBW;
        }

        return $description;
    }

    public function getFullDescriptionWithOccupations(): string
    {
        $description = $this->getFullDescription();

        if (!empty($this->occupations)) {
            $description .= ' (' . implode(', ', $this->occupations) . ')';
        }

        return $description;
    }

    public function getShortDescription(): string
    {
        $nameArray = array_filter([
            isset($this->firstName) ? mb_substr($this->firstName, 0, 1) . '.' : null,
            $this->lastName,
            $this->extra,
        ]);
        if (empty($nameArray)) {
            return $this->unprocessed;
        }

        return implode(' ', $nameArray);
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getFullDescription(),
        ];
    }

    public function getElastic(): array
    {
        return $this->getShortJson();
    }

    public static function sortByFullDescription($a, $b)
    {
        return strcmp($a->getFullDescription(), $b->getFullDescription());
    }
}
