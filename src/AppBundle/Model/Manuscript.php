<?php

namespace AppBundle\Model;

use AppBundle\Helpers\ArrayToJsonTrait;

class Manuscript extends Document
{
    use CacheDependenciesTrait;
    use ArrayToJsonTrait;

    private $diktyon;
    private $location;
    private $date;
    private $contentsWithParents;
    private $origin;
    private $patrons;
    /**
     * Array of arrays with one person and at least one occurrence
     * @var array
     */
    private $occurrencePatrons;
    private $scribes;
    /**
     * Array of arrays with one person and at least one occurrence
     * @var array
     */
    private $occurrenceScribes;
    private $relatedPersons;
    private $bibliographies;
    private $occurrences;
    private $publicComment;
    private $privateComment;
    private $illustrated;

    public function __construct()
    {
        $this->contentsWithParents = [];
        $this->patrons = [];
        $this->occurrencePatrons = [];
        $this->scribes = [];
        $this->occurrenceScribes = [];
        $this->relatedPersons = [];
        return $this;
    }

    public function setLocation(Location $location): Manuscript
    {
        $this->location = $location;

        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getName(): string
    {
        return $this->location->getName();
    }

    public function addContentWithParents(ContentWithParents $contentWithParents): Manuscript
    {
        $this->contentsWithParents[] = $contentWithParents;

        return $this;
    }

    public function getContentsWithParents(): array
    {
        return $this->contentsWithParents;
    }

    public function addPatron(Person $person): Manuscript
    {
        $this->patrons[$person->getId()] = $person;

        return $this;
    }

    public function getPatrons(): array
    {
        return $this->patrons;
    }

    public function addOccurrencePatron(Person $person, Occurrence $occurrence): Manuscript
    {
        if (isset($this->occurrencePatrons[$person->getId()])) {
            $this->occurrencePatrons[$person->getId()][] = $occurrence;
        }
        $this->occurrencePatrons[$person->getId()] = [$person, $occurrence];

        return $this;
    }

    public function getOccurrencePatrons(): array
    {
        return $this->occurrencePatrons;
    }

    public function getAllPatrons(): array
    {
        $patrons = $this->patrons;
        foreach ($this->occurrencePatrons as $occurrencePatron) {
            $occurrencePatronPerson = $occurrencePatron[0];
            if (!array_key_exists($occurrencePatronPerson->getId(), $patrons)) {
                $patrons[$occurrencePatronPerson->getId()] = $occurrencePatronPerson;
            }
        }
        return $patrons;
    }

    public function addScribe(Person $person): Manuscript
    {
        $this->scribes[$person->getId()] = $person;

        return $this;
    }

    public function getScribes(): array
    {
        return $this->scribes;
    }

    public function addOccurrenceScribe(Person $person, Occurrence $occurrence): Manuscript
    {
        if (isset($this->occurrenceScribes[$person->getId()])) {
            $this->occurrenceScribes[$person->getId()][] = $occurrence;
        }
        $this->occurrenceScribes[$person->getId()] = [$person, $occurrence];

        return $this;
    }

    public function getOccurrenceScribes(): array
    {
        return $this->occurrenceScribes;
    }

    public function getAllSCribes(): array
    {
        $scribes = $this->scribes;
        foreach ($this->occurrenceScribes as $occurrenceScribe) {
            $occurrenceScribePerson = $occurrenceScribe[0];
            if (!array_key_exists($occurrenceScribePerson->getId(), $scribes)) {
                $scribes[$occurrenceScribePerson->getId()] = $occurrenceScribePerson;
            }
        }
        return $scribes;
    }

    public function addRelatedPerson(Person $person): Manuscript
    {
        $this->relatedPersons[$person->getId()] = $person;

        return $this;
    }

    public function getRelatedPersons(): array
    {
        return $this->relatedPersons;
    }

    public function getOnlyRelatedPersons(): array
    {
        $persons = [];
        $allPatrons = $this->getAllPatrons();
        $allScribes = $this->getAllSCribes();
        foreach ($this->relatedPersons as $relatedPerson) {
            if (!array_key_exists($relatedPerson->getId(), $allPatrons)
                && !array_key_exists($relatedPerson->getId(), $allScribes)
            ) {
                $persons[$relatedPerson->getId()] = $relatedPerson;
            }
        }
        return $persons;
    }

    public function setDate(FuzzyDate $date): Manuscript
    {
        $this->date = $date;

        return $this;
    }

    public function getDate(): ?FuzzyDate
    {
        return $this->date;
    }

    public function setOrigin(Origin $origin): Manuscript
    {
        $this->origin = $origin;

        return $this;
    }

    public function getOrigin(): ?Origin
    {
        return $this->origin;
    }

    public function setBibliographies(array $bibliographies): Manuscript
    {
        $this->bibliographies = $bibliographies;

        return $this;
    }

    public function getBibliographies(): ?array
    {
        return $this->bibliographies;
    }

    public function setDiktyon(int $diktyon): Manuscript
    {
        $this->diktyon = $diktyon;

        return $this;
    }

    public function getDiktyon(): ?int
    {
        return $this->diktyon;
    }

    public function setPublicComment(string $publicComment = null): Manuscript
    {
        $this->publicComment = $publicComment;

        return $this;
    }

    public function getPublicComment(): ?string
    {
        return $this->publicComment;
    }

    public function setPrivateComment(string $privateComment = null): Manuscript
    {
        $this->privateComment = $privateComment;

        return $this;
    }

    public function getPrivateComment(): ?string
    {
        return $this->privateComment;
    }

    public function setOccurrences(array $occurrences): Manuscript
    {
        $this->occurrences = $occurrences;

        return $this;
    }

    public function getOccurrences(): ?array
    {
        return $this->occurrences;
    }

    public function setIllustrated(bool $illustrated): Manuscript
    {
        $this->illustrated = $illustrated;

        return $this;
    }

    public function getIllustrated(): ?bool
    {
        return $this->illustrated;
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'location' => $this->location->getJson(),
            'name' => $this->getName(),
            'patrons' => self::arrayToShortJson(array_values($this->patrons)),
            'occurrencePatrons' =>self::getOccurrencePersonsJson($this->occurrencePatrons),
            'scribes' => self::arrayToShortJson(array_values($this->scribes)),
            'occurrenceScribes' =>self::getOccurrencePersonsJson($this->occurrenceScribes),
        ];

        return $result;
    }

    public function getElastic(): array
    {
        $result = $this->getJson();
        if (!empty($this->contentsWithParents)) {
            $contents = [];
            foreach ($this->contentsWithParents as $contentWithParents) {
                $contents = array_merge($contents, $contentWithParents->getElastic());
            }
            $result['content'] = $contents;
        }
        if (isset($this->date) && !empty($this->date->getFloor())) {
            $result['date_floor_year'] = intval($this->date->getFloor()->format('Y'));
        }
        if (isset($this->date) && !empty($this->date->getCeiling())) {
            $result['date_ceiling_year'] = intval($this->date->getCeiling()->format('Y'));
        }
        if (!empty($this->getAllPatrons())) {
            $result['patron'] = [];
            foreach ($this->getAllPatrons() as $patron) {
                $result['patron'][] = $patron->getElastic();
            }
        }
        if (!empty($this->getAllSCribes())) {
            $result['scribe'] = [];
            foreach ($this->getAllSCribes() as $scribe) {
                $result['scribe'][] = $scribe->getElastic();
            }
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin->getElastic();
        }

        return $result;
    }

    private static function getOccurrencePersonsJson(array $occurrencePersons): array
    {
        $result = [];
        foreach ($occurrencePersons as $occurrencePerson) {
            $person = array_shift($occurrencePerson);
            $row = $person->getShortJson();
            $row['occurrences'] = array_map(
                function ($occurrence) {
                    return $occurrence->getDescription();
                },
                $occurrencePerson
            );
            $result[] = $row;
        }
        return $result;
    }
}
