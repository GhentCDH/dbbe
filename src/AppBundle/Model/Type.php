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

    /**
     * @var array
     */
    protected $keywords = [];
    /**
     * @var array
     */
    protected $occurrences = [];
    /**
     * Array containing related types and relation types
     * Structure:
     *  [
     *      [type, [typeRelationType, typeRelationType]],
     *      [type, [typeRelationType, typeRelationType]],
     *      ...
     *  ]
     * @var array
     */
    protected $relatedTypes = [];
    /**
     * @var Status
     */
    protected $criticalStatus;
    /**
     * @var string
     */
    protected $criticalApparatus;
    /**
     * @var string
     */
    protected $translation;
    /**
     * @var Occurrence
     */
    protected $basedOn;

    public function setVerses(array $verses): Type
    {
        $this->verses = $verses;

        return $this;
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

    public function addRelatedType(Type $type, TypeRelationType $relationType): Type
    {
        if (!isset($this->relatedTypes[$type->getId()])) {
            $this->relatedTypes[$type->getId()] = [$type, []];
        }
        $this->relatedTypes[$type->getId()][1][] = $relationType;

        return $this;
    }

    public function getRelatedTypes(): array
    {
        return $this->relatedTypes;
    }

    public function getPublicRelatedTypes(): array
    {
        return array_filter(
            $this->relatedTypes,
            function ($relatedType) {
                return $relatedType[0]->getPublic();
            }
        );
    }

    public function setCriticalStatus(Status $criticalStatus = null): Type
    {
        $this->criticalStatus = $criticalStatus;

        return $this;
    }

    public function getCriticalStatus(): ?Status
    {
        return $this->criticalStatus;
    }

    public function setCriticalApparatus(string $criticalApparatus = null): Type
    {
        $this->criticalApparatus = $criticalApparatus;

        return $this;
    }

    public function getCriticalApparatus(): ?string
    {
        return $this->criticalApparatus;
    }

    public function setTranslation(string $translation = null): Type
    {
        $this->translation = $translation;

        return $this;
    }

    public function getTranslation(): ?string
    {
        return $this->translation;
    }

    public function setBasedOn(Occurrence $basedOn = null): Type
    {
        $this->basedOn = $basedOn;

        return $this;
    }

    public function getBasedOn(): ?Occurrence
    {
        return $this->basedOn;
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

    public function getJson(): array
    {
        $result = parent::getJson();

        if (!empty($this->verses)) {
            $result['verses'] = implode("\n", $this->verses);
        }

        if (!empty($this->keywords)) {
            $result['keywords'] = ArrayToJson::arrayToShortJson($this->keywords);
        }

        if (!empty($this->relatedTypes)) {
            $result['relatedTypes'] = [];
            foreach ($this->relatedTypes as $relationType) {
                $result['relatedTypes'][] = [
                    'type' => $relationType[0]->getShortJson(),
                    'relationTypes' =>  ArrayToJson::arrayToShortJson($relationType[1]),
                ];
            }
        }
        if (isset($this->textStatus)) {
            $result['textStatus'] = $this->textStatus->getShortJson();
        }
        if (isset($this->criticalStatus)) {
            $result['criticalStatus'] = $this->criticalStatus->getShortJson();
        }
        if (isset($this->translation)) {
            $result['translation'] = $this->translation;
        }
        if (isset($this->criticalApparatus)) {
            $result['criticalApparatus'] = $this->criticalApparatus;
        }
        if (isset($this->basedOn)) {
            $result['basedOn'] = $this->basedOn->getShortJson();
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['number_of_occurrences'] = count($this->occurrences);

        if (!empty($this->verses)) {
            $result['verses'] = implode("\n", $this->verses);
        }
        if (!empty($this->textStatus)) {
            $result['text_status'] = $this->textStatus->getShortJson();
        }
        if (!empty($this->criticalStatus)) {
            $result['critical_status'] = $this->criticalStatus->getShortJson();
        }
        if (!empty($this->keywords)) {
            $result['keyword'] = ArrayToJson::arrayToShortJson($this->keywords);
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

        return $result;
    }
}
