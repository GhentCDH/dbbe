<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Manuscript extends Document implements IdJsonInterface
{
    use CacheDependenciesTrait;

    private $diktyon;
    private $locatedAt;
    private $contentsWithParents;
    private $origin;
    /**
     * Array of arrays with one person and at least one occurrence
     * @var array
     */
    private $occurrencePatrons;
    /**
     * Array of arrays with one person and at least one occurrence
     * @var array
     */
    private $occurrenceScribes;
    private $relatedPersons;
    /**
     * Array of occurrences, in order
     * @var array
     */
    private $occurrences;
    private $status;
    private $illustrated;

    public function __construct()
    {
        parent::__construct();

        $this->contentsWithParents = [];
        $this->occurrencePatrons = [];
        $this->occurrenceScribes = [];
        $this->relatedPersons = [];

        return $this;
    }

    public function setLocatedAt(LocatedAt $locatedAt): Manuscript
    {
        $this->locatedAt = $locatedAt;
        $this->addCacheDependency('located_at.' . $locatedAt->getId());
        foreach ($locatedAt->getCacheDependencies() as $cacheDependency) {
            $this->addCacheDependency($cacheDependency);
        }

        return $this;
    }

    public function getLocatedAt(): LocatedAt
    {
        return $this->locatedAt;
    }

    public function getName(): string
    {
        return $this->locatedAt->getName();
    }

    public function addContentWithParents(ContentWithParents $contentWithParents): Manuscript
    {
        $this->contentsWithParents[$contentWithParents->getId()] = $contentWithParents;

        return $this;
    }

    public function getContentsWithParents(): array
    {
        return $this->contentsWithParents;
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
            $occurrencePatronPerson = array_shift($occurrencePatron);
            if (!array_key_exists($occurrencePatronPerson->getId(), $patrons)) {
                // if all occurrences linked to a person are not public, indicate person as not public
                if ($occurrencePatronPerson->getPublic()) {
                    $public = false;
                    foreach ($occurrencePatron as $occurrence) {
                        if ($occurrence->getPublic()) {
                            $public = true;
                        }
                    }
                    if (!$public) {
                        $occurrencePatronPerson->setPublic(false);
                    }
                    $patrons[$occurrencePatronPerson->getId()] = $occurrencePatronPerson;
                }
            }
        }
        return $patrons;
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
            $occurrenceScribePerson = array_shift($occurrenceScribe);
            if (!array_key_exists($occurrenceScribePerson->getId(), $scribes)) {
                // if all occurrences linked to a person are not public, indicate person as not public
                if ($occurrenceScribePerson->getPublic()) {
                    $public = false;
                    foreach ($occurrenceScribe as $occurrence) {
                        if ($occurrence->getPublic()) {
                            $public = true;
                        }
                    }
                    if (!$public) {
                        $occurrenceScribePerson->setPublic(false);
                    }
                    $scribes[$occurrenceScribePerson->getId()] = $occurrenceScribePerson;
                }
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

    public function setOrigin(Origin $origin): Manuscript
    {
        $this->origin = $origin;
        $this->addCacheDependency('location.' . $origin->getId());

        return $this;
    }

    public function getOrigin(): ?Origin
    {
        return $this->origin;
    }

    public function setDiktyon(int $diktyon = null): Manuscript
    {
        $this->diktyon = $diktyon;

        return $this;
    }

    public function getDiktyon(): ?int
    {
        return $this->diktyon;
    }

    public function addOccurrence(Occurrence $occurrence): Manuscript
    {
        $this->occurrences[] = $occurrence;

        return $this;
    }

    public function getOccurrences(): ?array
    {
        return $this->occurrences;
    }

    public function setStatus(Status $status): Manuscript
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setIllustrated(bool $illustrated = null): Manuscript
    {
        $this->illustrated = empty($illustrated) ? false : $illustrated;

        return $this;
    }

    public function getIllustrated(): ?bool
    {
        return $this->illustrated;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getName(),
        ];
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'locatedAt' => $this->locatedAt->getJson(),
            'name' => $this->getName(),
            'content' => ArrayToJson::arrayToShortJson($this->contentsWithParents),
            'patrons' => ArrayToJson::arrayToShortJson($this->patrons),
            'occurrencePatrons' => self::getOccurrencePersonsJson($this->occurrencePatrons),
            'scribes' => ArrayToJson::arrayToShortJson($this->scribes),
            'relatedPersons' => ArrayToJson::arrayToShortJson($this->relatedPersons),
            'occurrenceScribes' => self::getOccurrencePersonsJson($this->occurrenceScribes),
            'bibliography' => ArrayToJson::arrayToShortJson($this->getBibliographies()),
            'public' => $this->public,
        ];

        if (isset($this->date)) {
            $result['date'] = $this->date->getJson();
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin->getShortJson();
        }
        if (isset($this->diktyon)) {
            $result['diktyon'] = $this->diktyon;
        }
        if (isset($this->publicComment)) {
            $result['publicComment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['privateComment'] = $this->privateComment;
        }
        if (isset($this->occurrences)) {
            $result['occurrences'] = ArrayToJson::arrayToShortJson($this->occurrences);
        }
        if (isset($this->status)) {
            $result['status'] = $this->status->getShortJson();
        }
        if (isset($this->illustrated)) {
            $result['illustrated'] = $this->illustrated;
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'city' => $this->locatedAt->getLocation()->getRegionWithParents()->getIndividualJson(),
            'library' => $this->locatedAt->getLocation()->getInstitution()->getJson(),
            'shelf' => $this->locatedAt->getShelf(),
            'name' => $this->getName(),
            'public' => $this->getPublic(),
        ];
        if ($this->locatedAt->getLocation()->getCollection() != null) {
            $result['collection'] = $this->locatedAt->getLocation()->getCollection()->getJson();
        }
        if (!empty($this->contentsWithParents)) {
            $contents = [];
            foreach ($this->contentsWithParents as $contentWithParents) {
                $contents = array_merge($contents, $contentWithParents->getShortElastic());
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
                $result['patron'][] = $patron->getShortJson();
            }
        }
        if (!empty($this->getAllSCribes())) {
            $result['scribe'] = [];
            foreach ($this->getAllSCribes() as $scribe) {
                $result['scribe'][] = $scribe->getShortJson();
            }
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin->getShortElastic();
        }
        if (isset($this->publicComment)) {
            $result['public_comment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['private_comment'] = $this->privateComment;
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
