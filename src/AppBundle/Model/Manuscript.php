<?php

namespace AppBundle\Model;

class Manuscript
{
    use CacheDependencies;

    private $id;
    private $diktyon;
    private $city;
    private $library;
    private $collection;
    private $shelf;
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

    public function setCity(City $city): Manuscript
    {
        $this->city = $city;

        return $this;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setLibrary(Library $library): Manuscript
    {
        $this->library = $library;

        return $this;
    }

    public function getLibrary(): Library
    {
        return $this->library;
    }

    public function setCollection(Collection $collection): Manuscript
    {
        $this->collection = $collection;

        return $this;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function setShelf(string $shelf): Manuscript
    {
        $this->shelf = $shelf;

        return $this;
    }

    public function getShelf(): string
    {
        return $this->shelf;
    }

    public function getName(): string
    {
        $name = strtoupper($this->city->getName());
        $name .= ' - ' . $this->library->getName();
        if (!empty($this->collection)) {
            $name .= ' - ' . $this->collection->getName();
        }
        $name .= ' ' . $this->shelf;

        return $name;
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

    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'city' => $this->city->getElastic(),
            'library' => $this->library->getElastic(),
            'shelf' => $this->shelf,
            'name' => $this->getName(),
        ];

        if (isset($this->collection)) {
            $result['collection'] = $this->collection->getElastic();
        }
        if (isset($this->contentsWithParents)) {
            $contents = [];
            foreach ($this->contentsWithParents as $contentWithParents) {
                $contents = array_merge($contents, $contentWithParents->getElastic());
            }
            $result['content'] = $contents;
        }
        if (isset($this->date) && !empty($this->date->getFloor())) {
            $result['date_floor_year'] = $this->date->getFloor()->format('Y');
        }
        if (isset($this->date) && !empty($this->date->getCeiling())) {
            $result['date_ceiling_year'] = $this->date->getCeiling()->format('Y');
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
