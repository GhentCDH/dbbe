<?php

namespace AppBundle\Model;

class Manuscript
{
    use CacheDependenciesTrait;

    private $id;
    private $diktyon;
    private $location;
    private $date;
    private $contentsWithParents;
    private $origin;
    private $patrons;
    private $scribes;
    private $relatedPersons;
    private $bibliographies;
    private $occurrences;
    private $publicComment;
    private $privateComment;
    private $illustrated;

    public function __construct()
    {
        return $this;
    }

    public function setId(int $id): Manuscript
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
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
        if (!isset($this->contentsWithParents)) {
            $this->contentsWithParents = [];
        }
        $this->contentsWithParents[] = $contentWithParents;

        return $this;
    }

    public function getContentsWithParents(): ?array
    {
        return $this->contentsWithParents;
    }

    public function addPatron(Person $person): Manuscript
    {
        if (!isset($this->patrons)) {
            $this->patrons = [];
        }
        $this->patrons[] = $person;

        return $this;
    }

    public function getPatrons(): ?array
    {
        return $this->patrons;
    }

    public function addScribe(Person $person): Manuscript
    {
        if (!isset($this->scribes)) {
            $this->scribes = [];
        }
        $this->scribes[] = $person;

        return $this;
    }

    public function getScribes(): ?array
    {
        return $this->scribes;
    }

    public function addRelatedPerson(Person $person): Manuscript
    {
        if (!isset($this->relatedPersons)) {
            $this->relatedPersons = [];
        }
        $this->relatedPersons[] = $person;

        return $this;
    }

    public function getRelatedPersons(): ?array
    {
        return $this->relatedPersons;
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
        ];

        return $result;
    }

    public function getElastic(): array
    {
        $result = $this->getJson();
        if (isset($this->contentsWithParents)) {
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
        if (isset($this->patrons)) {
            $result['patron'] = [];
            foreach ($this->patrons as $patron) {
                $result['patron'][] = $patron->getElastic();
            }
        }
        if (isset($this->scribes)) {
            $result['scribe'] = [];
            foreach ($this->scribes as $scribe) {
                $result['scribe'][] = $scribe->getElastic();
            }
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin->getElastic();
        }

        return $result;
    }
}
