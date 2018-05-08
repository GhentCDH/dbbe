<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Occurrence
{
    use CacheDependenciesTrait;

    private $id;
    private $foliumStart;
    private $foliumStartRecto;
    private $foliumEnd;
    private $foliumEndRecto;
    private $generalLocation;
    private $type;
    private $manuscript;
    private $incipit;
    private $text;
    private $meter;
    private $genre;
    private $subjects;
    private $patrons;
    private $scribes;
    private $date;
    private $public;

    public function __construct()
    {
        $this->subjects = [];
        $this->patrons = [];
        $this->scribes = [];
        return $this;
    }

    public function setId(int $id): Occurrence
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setFoliumStart(string $foliumStart = null): Occurrence
    {
        $this->foliumStart = $foliumStart;

        return $this;
    }

    public function setFoliumStartRecto(string $foliumStartRecto = null): Occurrence
    {
        $this->foliumStartRecto = $foliumStartRecto;

        return $this;
    }

    public function setFoliumEnd(string $foliumEnd = null): Occurrence
    {
        $this->foliumEnd = $foliumEnd;

        return $this;
    }

    public function setFoliumEndRecto(string $foliumEndRecto = null): Occurrence
    {
        $this->foliumEndRecto = $foliumEndRecto;

        return $this;
    }

    public function setGeneralLocation(string $generalLocation = null): Occurrence
    {
        $this->generalLocation = $generalLocation;

        return $this;
    }

    public function setType(Type $type = null): Occurrence
    {
        $this->type = $type;

        return $this;
    }

    public function setManuscript(Manuscript $manuscript = null): Occurrence
    {
        $this->manuscript = $manuscript;

        return $this;
    }

    public function setIncipit(string $incipit = null): Occurrence
    {
        $this->incipit = $incipit;

        return $this;
    }

    public function setText(string $text = null): Occurrence
    {
        $this->text = $text;

        return $this;
    }

    public function setMeter(Meter $meter = null): Occurrence
    {
        $this->meter = $meter;

        return $this;
    }

    public function setGenre(Genre $genre): Occurrence
    {
        $this->genre = $genre;

        return $this;
    }

    public function addSubject(SubjectInterface $subject): Occurrence
    {
        $this->subjects[$subject->getId()] = $subject;

        return $this;
    }

    public function addPatron(Person $person): Occurrence
    {
        $this->patrons[$person->getId()] = $person;

        return $this;
    }

    public function addScribe(Person $person): Occurrence
    {
        $this->scribes[$person->getId()] = $person;

        return $this;
    }

    public function setDate(FuzzyDate $date): Occurrence
    {
        $this->date = $date;

        return $this;
    }

    public function setPublic(bool $public): Occurrence
    {
        $this->public = $public;

        return $this;
    }

    public function getPublic(): ?bool
    {
        return $this->public;
    }

    public function getDescription(): string
    {
        $result = '';
        if (!empty($this->foliumStart)) {
            if (!empty($this->foliumEnd)) {
                $result .= '(f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto)
                    . '-' . $this->foliumEnd . self::formatRecto($this->foliumEndRecto) . ') ';
            } else {
                $result .= '(f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto) . ') ';
            }
        }

        if (!empty($this->generalLocation)) {
            $result .= '(' . $this->generalLocation . ') ';
        }

        if (!empty($this->incipit)) {
            $result .= $this->incipit;
        }
        return $result;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'public' => $this->getPublic(),
        ];

        if (isset($this->incipit)) {
            $result['incipit'] = $this->incipit;
        }
        if (isset($this->text)) {
            $result['text'] = $this->text;
        }
        if (isset($this->meter)) {
            $result['meter'] = $this->meter->getShortJson();
        }
        if (!empty($this->subjects)) {
            $result['subject'] = ArrayToJson::arrayToShortJson($this->subjects);
        }
        if (!empty($this->manuscript)) {
            $result['manuscript'] = $this->manuscript->getShortJson();
            if (!empty($this->manuscript->getContentsWithParents())) {
                $contents = [];
                foreach ($this->manuscript->getContentsWithParents() as $contentWithParents) {
                    $contents = array_merge($contents, $contentWithParents->getElastic());
                }
                $result['manuscript_content'] = $contents;
            }
        }
        if (!empty($this->patrons)) {
            $result['patron'] = [];
            foreach ($this->patrons as $patron) {
                $result['patron'][] = $patron->getShortJson();
            }
        }
        if (!empty($this->scribes)) {
            $result['scribe'] = [];
            foreach ($this->scribes as $scribe) {
                $result['scribe'][] = $scribe->getShortJson();
            }
        }
        if (isset($this->date) && !empty($this->date->getFloor())) {
            $result['date_floor_year'] = intval($this->date->getFloor()->format('Y'));
        }
        if (isset($this->date) && !empty($this->date->getCeiling())) {
            $result['date_ceiling_year'] = intval($this->date->getCeiling()->format('Y'));
        }
        if (isset($this->genre)) {
            $result['genre'] = $this->genre->getShortJson();
        }

        return $result;
    }

    private static function formatRecto(bool $recto = null): string
    {
        if (empty($recto)) {
            return '';
        }

        if ($recto) {
            return 'r';
        } else {
            return 'v';
        }
    }
}
