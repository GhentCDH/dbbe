<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

/**
 */
class Poem extends Document
{
    /**
     * @var string
     */
    protected $incipit;
    /**
     * Array of Verses for Occurrence
     * Array of strings for Type
     * @var array
     */
    protected $verses = [];
    /**
     * @var int
     */
    protected $numberOfVerses;
    /**
     * @var array
     */
    protected $metres = [];
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

    public function setNumberOfVerses(int $numberOfVerses = null): Poem
    {
        $this->numberOfVerses = $numberOfVerses;

        return $this;
    }

    public function getNumberOfVerses(): int
    {
        return isset($this->numberOfVerses) ? $this->numberOfVerses : count($this->verses);
    }

    public function addMetre(Metre $metre = null): Poem
    {
        $this->metres[$metre->getId()] = $metre;

        return $this;
    }

    public function getMetres(): array
    {
        return $this->metres;
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

    public function setSubjects(array $subjects): Poem
    {
        $this->subjects = $subjects;

        return $this;
    }

    public function addSubject(SubjectInterface $subject): Poem
    {
        $this->subjects[$subject->getId()] = $subject;

        return $this;
    }

    public function delSubjectById(int $subjectId): Poem
    {
        foreach ($this->subjects as $key => $subject) {
            if ($subject->getId() == $subjectId) {
                unset($this->subjects[$key]);
                break;
            }
        }

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

    public function getDBBE(): bool
    {
        $textSources = $this->getTextSources();
        foreach ($textSources as $textSource) {
            if ($textSource->getType() == 'onlineSource' && $textSource->getOnlineSource()->getName() == 'DBBE') {
                return true;
            }
        }
        return false;
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        $result['incipit'] = $this->incipit;

        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->numberOfVerses)) {
            $result['numberOfVerses'] = $this->numberOfVerses;
        }
        if (!empty($this->metres)) {
            $result['metres'] = ArrayToJson::arrayToShortJson($this->metres);
        }
        $result['subjects'] = [
            'persons' => ArrayToJson::arrayToShortJson($this->getPersonSubjects()),
            'keywords' => ArrayToJson::arrayToShortJson($this->getKeywordSubjects()),
        ];
        if (!empty($this->genres)) {
            $result['genres'] = ArrayToJson::arrayToShortJson($this->genres);
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['dbbe'] = $this->getDBBE();
        $result['incipit'] = $this->incipit;

        if (!empty($this->prevId)) {
            $result['prev_id'] = $this->prevId;
        }

        if (!empty($this->title)) {
            $result['title_stemmer'] = $this->title;
            $result['title_original'] = $this->title;
        }
        if (!empty($this->metres)) {
            $result['metre'] = ArrayToJson::arrayToShortJson($this->metres);
        }
        if (!empty($this->subjects)) {
            $result['subject'] = ArrayToJson::arrayToShortJson($this->subjects);
        }
        foreach ($this->getPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        foreach ($this->getPublicPersonRoles() as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (!empty($this->genres)) {
            $result['genre'] =  ArrayToJson::arrayToShortJson($this->genres);
        }

        return $result;
    }
}
