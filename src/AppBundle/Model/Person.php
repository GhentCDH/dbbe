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
    private $occupations;
    private $historical;
    private $manuscripts;

    public function __construct()
    {
        parent::__construct();
        
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setLastName(string $lastName = null): Person
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setExtra(string $extra = null): Person
    {
        $this->extra = $extra;

        return $this;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setUnprocessed(string $unprocessed = null): Person
    {
        $this->unprocessed = $unprocessed;

        return $this;
    }

    public function getUnprocessed(): ?string
    {
        return $this->unprocessed;
    }

    public function setBornDate(FuzzyDate $bornDate = null): Person
    {
        $this->bornDate = $bornDate;

        return $this;
    }

    public function getBornDate(): ?FuzzyDate
    {
        return $this->bornDate;
    }

    public function setDeathDate(FuzzyDate $deathDate = null): Person
    {
        $this->deathDate = $deathDate;

        return $this;
    }

    public function getDeathDate(): ?FuzzyDate
    {
        return $this->deathDate;
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

    public function getHistorical(): bool
    {
        return $this->historical;
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
        foreach ($this->identifications as $identification) {
            if ($identification->getIdentifier()->getPrimary()) {
                $description .= ' - ' . $identification->getIdentifier()->getName() . ': ' . implode(', ', $identification->getIdentifications());
            }
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
        $result = parent::getJson();

        $result['name'] = $this->getFullDescriptionWithOccupations();
        $result['historical'] = $this->historical;

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
        if (!empty($this->getTypes())) {
            $result['types'] = ArrayToJson::arrayToShortJson($this->getTypes());
        }
        if (!empty($this->getFunctions())) {
            $result['functions'] = ArrayToJson::arrayToShortJson($this->getFunctions());
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getJson();

        $result['name'] = $this->getName();
        $result['historical'] = $this->historical;

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
        if (!empty($this->getTypes())) {
            $result['type'] = ArrayToJson::arrayToShortJson($this->getTypes());
        }
        if (!empty($this->getFunctions())) {
            $result['function'] = ArrayToJson::arrayToShortJson($this->getFunctions());
        }

        return $result;
    }

    public static function sortByFullDescription($a, $b)
    {
        return strcmp($a->getFullDescription(), $b->getFullDescription());
    }
}
