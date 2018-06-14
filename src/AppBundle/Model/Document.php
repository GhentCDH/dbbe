<?php

namespace AppBundle\Model;

class Document
{
    protected $id;
    protected $prevId;
    protected $date;
    protected $patrons;
    protected $scribes;
    protected $bibliographies;
    protected $publicComment;
    protected $privateComment;
    protected $public;

    public function __construct()
    {
        $this->patrons = [];
        $this->scribes = [];
        $this->bibliographies = [];

        return $this;
    }

    public function setId(int $id): Document
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
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

    public function setBibliographies(array $bibliographies): Document
    {
        $this->bibliographies = $bibliographies;

        return $this;
    }

    public function getBibliographies(): array
    {
        return $this->bibliographies;
    }

    public function setPublicComment(string $publicComment = null): Document
    {
        $this->publicComment = $publicComment;

        return $this;
    }

    public function getPublicComment(): ?string
    {
        return $this->publicComment;
    }

    public function setPrivateComment(string $privateComment = null): Document
    {
        $this->privateComment = $privateComment;

        return $this;
    }

    public function getPrivateComment(): ?string
    {
        return $this->privateComment;
    }

    public function setPublic(bool $public): Document
    {
        $this->public = $public;

        return $this;
    }

    public function getPublic(): ?bool
    {
        return $this->public;
    }
}
