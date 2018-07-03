<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Document extends Entity
{
    protected $prevId;
    protected $date;
    protected $patrons;
    protected $scribes;
    protected $bibliographies;

    public function __construct()
    {
        parent::__construct();

        $this->patrons = [];
        $this->scribes = [];
        $this->bibliographies = [];

        return $this;
    }

    public function setPrevId(int $prevId): Occurrence
    {
        $this->prevId = $prevId;

        return $this;
    }

    public function getPrevId(): ?int
    {
        return $this->prevId;
    }

    public function setDate(FuzzyDate $date): Document
    {
        $this->date = $date;

        return $this;
    }

    public function getDate(): ?FuzzyDate
    {
        return $this->date;
    }
    public function addPatron(Person $person): Document
    {
        $this->patrons[$person->getId()] = $person;

        return $this;
    }

    public function getPatrons(): array
    {
        return $this->patrons;
    }

    public function addScribe(Person $person): Document
    {
        $this->scribes[$person->getId()] = $person;

        return $this;
    }

    public function getScribes(): array
    {
        return $this->scribes;
    }

    public function addBibliography(Bibliography $bibliography): Document
    {
        $this->bibliographies[$bibliography->getId()] = $bibliography;

        return $this;
    }

    public function getBibliographies(): array
    {
        return $this->bibliographies;
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        if (!empty($this->patrons)) {
            $result['patrons'] = ArrayToJson::arrayToShortJson($this->patrons);
        }
        if (!empty($this->scribes)) {
            $result['scribes'] = ArrayToJson::arrayToShortJson($this->scribes);
        }

        if (!empty($this->getBibliographies())) {
            $result['bibliography'] = ArrayToJson::arrayToShortJson($this->getBibliographies());
        }

        return $result;
    }
}
