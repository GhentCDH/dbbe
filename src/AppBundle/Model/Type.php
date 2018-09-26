<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

/**
 */
class Type extends Poem
{
    /**
     * @var string
     */
    const CACHENAME = 'type';

    use CacheLinkTrait;

    /**
     * @var array
     */
    protected $keywords = [];
    /**
     * @var array
     */
    protected $occurrences = [];
    /**
     * @var array
     */
    protected $relatedTypes = [];

    public function setVerses(array $verses): Type
    {
        $this->verses = $verses;

        return $this;
    }

    public function getNumberOfVerses(): ?int
    {
        return count($this->verses);
    }

    public function addKeyword(Keyword $keyword): Type
    {
        $this->keywords[] = $keyword;

        return $this;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function addOccurrence(Occurrence $occurrence): Type
    {
        $this->occurrences[] = $occurrence;

        return $this;
    }

    public function getOccurrences(): array
    {
        return $this->occurrences;
    }

    public function setRelatedTypes(array $types): Type
    {
        $this->relatedTypes = $types;

        return $this;
    }

    public function getRelatedTypes(): array
    {
        return $this->relatedTypes;
    }

    public function getDescription(): string
    {
        return $this->incipit;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->incipit,
        ];
    }

    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'public' => $this->public,
        ];

        if (isset($this->incipit)) {
            $result['incipit'] = $this->incipit;
        }
        if (isset($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->verses)) {
            $result['text'] = implode("\n", $this->verses);
        }
        if (isset($this->textStatus)) {
            $result['text_status'] = $this->textStatus->getShortJson();
        }
        if (isset($this->meters)) {
            $result['meter'] = ArrayToJson::arrayToShortJson($this->meters);
        }
        if (!empty($this->subjects)) {
            $result['subject'] = ArrayToJson::arrayToShortJson($this->subjects);
        }
        if (!empty($this->keywords)) {
            $result['keyword'] = ArrayToJson::arrayToShortJson($this->keywords);
        }
        foreach ($this->getPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        foreach ($this->getPublicPersonRoles() as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($this->genres)) {
            $result['genre'] =  ArrayToJson::arrayToShortJson($this->genres);
        }
        if (!empty($this->occurrences)) {
            $result['number_of_occurrences'] = count($this->occurrences);
        }
        if (!empty($this->occurrences)) {
            $result['number_of_occurrences_public'] = count(
                array_filter(
                    $this->occurrences,
                    function ($occurrence) {
                        return $occurrence->getPublic();
                    }
                )
            );
        }
        if (isset($this->publicComment)) {
            $result['public_comment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['private_comment'] = $this->privateComment;
        }

        return $result;
    }
}
