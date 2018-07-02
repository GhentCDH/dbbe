<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Person extends Entity implements SubjectInterface, IdJsonInterface
{
    use CacheDependenciesTrait;

    private $firstName;
    private $lastName;
    private $extra;
    private $unprocessed;
    private $bornDate;
    private $deathDate;
    // array: a person can have an id in all 3 books
    private $RGK;
    private $VGH;
    private $PBW;
    private $occupations;
    private $historical;
    private $manuscripts;

    public function __construct()
    {
        $this->RGK = [];
        $this->VGH = [];
        $this->occupations = [];
        $this->manuscripts = [
            'patron' => [],
            'scribe' => [],
            'related' => [],
        ];

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

    public function getBornDate(): FuzzyDate
    {
        return $this->bornDate;
    }

    public function setDeathDate(FuzzyDate $deathDate = null): Person
    {
        $this->deathDate = $deathDate;

        return $this;
    }

    public function getDeathDate(): FuzzyDate
    {
        return $this->deathDate;
    }

    // called for each entry
    public function addRGK(string $volume, string $rgk): Person
    {
        $this->RGK[] = $volume . '.' . $rgk;

        return $this;
    }

    public function getRGK(): array
    {
        return $this->RGK;
    }

    // called once; comma separated list
    public function setVGH(string $vgh): Person
    {
        $this->VGH = explode(', ', $vgh);

        return $this;
    }

    public function getVGH(): array
    {
        return $this->VGH;
    }

    public function setPBW(string $pbw): Person
    {
        $this->PBW = $pbw;

        return $this;
    }

    public function getPBW(): string
    {
        return $this->PBW;
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

    public function addManuscript(Manuscript $manuscript, string $type): Person
    {
        // Only add manuscript of not yet in the array of this type
        if (!array_key_exists($manuscript->getId(), $this->manuscripts[$type])) {
            $this->manuscripts[$type][$manuscript->getId()] = $manuscript;
        }

        return $this;
    }

    public function getPatronManuscripts(): array
    {
        $patron = $this->manuscripts['patron'];
        usort(
            $patron,
            function ($a, $b) {
                return strcmp($a->getName(), $b->getName());
            }
        );
        return ($patron);
    }

    public function getScribeManuscripts(): array
    {
        $scribe = $this->manuscripts['scribe'];
        usort(
            $scribe,
            function ($a, $b) {
                return strcmp($a->getName(), $b->getName());
            }
        );
        return ($scribe);
    }

    public function getRelatedManuscripts(): array
    {
        // only return manuscripts with related link that do not have a patron or scribe link
        $onlyRelated = array_diff_key($this->manuscripts['related'], $this->manuscripts['patron'], $this->manuscripts['scribe']);
        usort(
            $onlyRelated,
            function ($a, $b) {
                return strcmp($a->getName(), $b->getName());
            }
        );
        return $onlyRelated;
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
        return array_filter($this->occupations, function ($occupation) {
            return !$occupation->getIsFunction();
        });
    }

    public function getFunctions(): array
    {
        return array_filter($this->occupations, function ($occupation) {
            return $occupation->getIsFunction();
        });
    }

    public function getIdentifications(): array
    {
        $result = [];
        foreach (['RGK', 'VGH'] as $id) {
            if (!empty($this->$id)) {
                $result[] = $id . ': ' . implode(', ', $this->$id);
            }
        }
        if (isset($this->PBW)) {
            $result[] = 'PBW' . ': ' . $this->PBW;
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
        if (!empty($this->RGK)) {
            $description .= ' - RGK: ' . implode(', ', $this->RGK);
        }
        if (!empty($this->VGH)) {
            $description .= ' - VGH: ' . implode(', ', $this->VGH);
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

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'historical' => $this->historical,
            'public' => $this->public,
        ];
        if (isset($this->firstName)) {
            $result['firstName'] = $this->firstName;
        }
        if (isset($this->lastName)) {
            $result['lastName'] = $this->lastName;
        }
        if (isset($this->extra)) {
            $result['extra'] = $this->extra;
        }
        if (isset($this->unprocessed)) {
            $result['unprocessed'] = $this->unprocessed;
        }
        if (isset($this->bornDate) && !($this->bornDate->isEmpty())) {
            $result['bornDate'] = $this->bornDate->getJson();
        }
        if (isset($this->deathDate) && !($this->deathDate->isEmpty())) {
            $result['deathDate'] = $this->deathDate->getJson();
        }
        if (!empty($this->RGK)) {
            $result['rgk'] = implode(', ', $this->RGK);
        }
        if (!empty($this->VGH)) {
            $result['vgh'] = implode(', ', $this->VGH);
        }
        if (isset($this->PBW)) {
            $result['pbw'] = $this->PBW;
        }
        if (!empty($this->getTypes())) {
            $result['types'] = ArrayToJson::arrayToShortJson($this->getTypes());
        }
        if (!empty($this->getFunctions())) {
            $result['functions'] = ArrayToJson::arrayToShortJson($this->getFunctions());
        }
        if (isset($this->publicComment)) {
            $result['publicComment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['privateComment'] = $this->privateComment;
        }

        return $result;
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
        if (!empty($this->RGK)) {
            $result['rgk'] = $this->RGK;
        }
        if (!empty($this->VGH)) {
            $result['vgh'] = $this->VGH;
        }
        if (isset($this->PBW)) {
            $result['pbw'] = $this->PBW;
        }
        if (!empty($this->getTypes())) {
            $result['type'] = ArrayToJson::arrayToShortJson($this->getTypes());
        }
        if (!empty($this->getFunctions())) {
            $result['function'] = ArrayToJson::arrayToShortJson($this->getFunctions());
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
