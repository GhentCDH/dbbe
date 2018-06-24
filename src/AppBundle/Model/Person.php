<?php

namespace AppBundle\Model;

class Person extends Entity implements SubjectInterface
{
    use CacheDependenciesTrait;

    private $firstName;
    private $lastName;
    private $extra;
    private $unprocessed;
    private $bornDate;
    private $deathDate;
    private $RGK;
    private $VGH;
    private $PBW;
    private $occupations;
    private $historical;

    public function __construct()
    {
        $this->occupations = [];

        return $this;
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

    public function setVGH(string $vgh): Person
    {
        $this->VGH = $vgh;

        return $this;
    }

    public function setPBW(string $pbw): Person
    {
        $this->PBW = $pbw;

        return $this;
    }

    public function addOccupation(Occupation $occupation): Person
    {
        $this->occupations[$occupation->getId()] = $occupation;

        return $this;
    }

    public function setHistorical(bool $historical = null): Person
    {
        $this->historical = empty($historical) ? false : $historical;

        return $this;
    }

    public function getName(): string
    {
        $nameArray = array_filter([
            $this->firstName,
            $this->lastName,
            $this->extra,
        ]);
        if (empty($nameArray)) {
            return $this->unprocessed;
        }
        return implode(' ', $nameArray);
    }

    public function getInterval(): FuzzyInterval
    {
        return new FuzzyInterval($this->bornDate, $this->deathDate);
    }

    public function getTypes(): array
    {
        if (empty($this->occupations)) {
            return [];
        }
        return array_filter($this->occupations, function ($occupation) {
            return !$occupation->getIsFunction();
        });
    }

    public function getFunctions(): array
    {
        if (empty($this->occupations)) {
            return [];
        }
        return array_filter($this->occupations, function ($occupation) {
            return $occupation->getIsFunction();
        });
    }

    public function getIdentifications(): array
    {
        $result = [];
        foreach (['RGK', 'VGH', 'PBW'] as $id) {
            if (isset($this->$id)) {
                $result[] = $id . ': ' . $this->$id;
            }
        }
        return $result;
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
        if (isset($this->VGH)) {
            $description .= ' - VGH: ' . $this->VGH;
        }
        if (isset($this->PBW)) {
            $description .= ' - PBW: ' . $this->PBW;
        }

        return $description;
    }

    public function getFullDescriptionWithOccupations(): string
    {
        $description = $this->getFullDescription();

        if (!empty($this->getFunctions())) {
            $description .= ' (' . implode(', ', $this->getFunctions()) . ')';
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
        $result = [
            'id' => $this->id,
            'name' => $this->getName(),
            'historical' => $this->historical,
            'public' => $this->public,
        ];
        if (isset($this->bornDate) && !empty($this->bornDate->getFloor())) {
            $result['born_date_floor_year'] = intval($this->bornDate->getFloor()->format('Y'));
        }
        if (isset($this->bornDate) && !empty($this->bornDate->getCeiling())) {
            $result['born_date_ceiling_year'] = intval($this->bornDate->getCeiling()->format('Y'));
        }
        if (isset($this->deathDate) && !empty($this->deathDate->getFloor())) {
            $result['death_date_floor_year'] = intval($this->deathDate->getFloor()->format('Y'));
        }
        if (isset($this->deathDate) && !empty($this->deathDate->getCeiling())) {
            $result['death_date_ceiling_year'] = intval($this->deathDate->getCeiling()->format('Y'));
        }
        if (isset($this->RGK)) {
            $result['rgk'] = $this->RGK;
        }
        if (isset($this->VGH)) {
            $result['vgh'] = $this->VGH;
        }
        if (isset($this->PBW)) {
            $result['pbw'] = $this->PBW;
        }
        if (!empty($this->getTypes())) {
            $result['type'] = [];
            foreach ($this->getTypes() as $typeOccupation) {
                $result['type'][] = $typeOccupation->getShortJson();
            }
        }
        if (!empty($this->getFunctions())) {
            $result['function'] = [];
            foreach ($this->getFunctions() as $functionOccupation) {
                $result['function'][] = $functionOccupation->getShortJson();
            }
        }
        if (isset($this->publicComment)) {
            $result['public_comment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['private_comment'] = $this->privateComment;
        }

        return $result;
    }

    public static function sortByFullDescription($a, $b)
    {
        return strcmp($a->getFullDescription(), $b->getFullDescription());
    }
}
