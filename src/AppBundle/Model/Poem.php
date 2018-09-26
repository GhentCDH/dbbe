<?php

namespace AppBundle\Model;

/**
 */
class Poem extends Document
{
    /**
     * @var string
     */
    protected $incipit;
    /**
     * @var string
     */
    protected $title;
    /**
     * Array of Verses for Occurrence
     * Array of strings for Type
     * @var array
     */
    protected $verses = [];
    /**
     * @var array
     */
    protected $meters = [];
    /**
     * @var array
     */
    protected $genres = [];
    /**
     * @var array
     */
    protected $subjects = [];
    /**
     * @var Status
     */
    protected $textStatus;
    /**
     * @var array
     */
    protected $acknowledgements = [];

    public function setIncipit(string $incipit): Poem
    {
        $this->incipit = $incipit;

        return $this;
    }

    public function getIncipit(): string
    {
        return $this->incipit;
    }

    public function getVerses(): array
    {
        return $this->verses;
    }

    public function addMeter(Meter $meter = null): Poem
    {
        $this->meters[$meter->getId()] = $meter;

        return $this;
    }

    public function getMeters(): array
    {
        return $this->meters;
    }

    public function addGenre(Genre $genre): Poem
    {
        $this->genres[$genre->getId()] = $genre;

        return $this;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }

    public function addSubject(SubjectInterface $subject): Poem
    {
        $this->subjects[$subject->getId()] = $subject;

        return $this;
    }

    public function getSubjects(): array
    {
        return $this->subjects;
    }

    public function sortSubjects(): void
    {
        usort(
            $this->subjects,
            function ($a, $b) {
                if (is_a($a, Person::class)) {
                    if (!is_a($b, Person::class)) {
                        return -1;
                    } else {
                        return strcmp($a->getFullDescriptionWithOffices(), $b->getFullDescriptionWithOffices());
                    }
                } else {
                    if (is_a($b, Person::class)) {
                        return 1;
                    } else {
                        return strcmp($a->getName(), $b->getName());
                    }
                }
            }
        );
    }

    public function getPersonSubjects(): array
    {
        return array_filter(
            $this->subjects,
            function ($subject) {
                return is_a($subject, Person::class);
            }
        );
    }

    public function getKeywordSubjects(): array
    {
        return array_filter(
            $this->subjects,
            function ($subject) {
                return is_a($subject, Keyword::class);
            }
        );
    }

    public function setTextStatus(Status $textStatus = null): Poem
    {
        $this->textStatus = $textStatus;

        return $this;
    }

    public function getTextStatus(): ?Status
    {
        return $this->textStatus;
    }

    public function getDescription(): string
    {
        return $this->incipit;
    }

    public function addAcknowledgement(Acknowledgement $acknowledgement): Poem
    {
        $this->acknowledgements[] = $acknowledgement;

        return $this;
    }

    public function getAcknowledgements(): array
    {
        return $this->acknowledgements;
    }

    public function sortAcknowledgements(): void
    {
        usort(
            $this->acknowledgements,
            function ($a, $b) {
                return strcmp($a->getName(), $b->getName());
            }
        );
    }
}
