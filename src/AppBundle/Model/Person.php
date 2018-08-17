<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Person extends Entity implements SubjectInterface
{
    use CacheDependenciesTrait;

    private $firstName;
    private $lastName;
    private $extra;
    private $unprocessed;
    private $bornDate;
    private $deathDate;
    private $offices;
    private $historical;
    private $modern;
    /**
     * Array containing all manuscriptroles
     * Structure:
     *  [
     *      role_system_name => [
     *          role,
     *          [
     *              manuscript_id => manuscript,
     *              manuscript_id => manuscript,
     *          ],
     *      ],
     *      role_system_name => [...],
     *  ]
     * @var array
     */
    private $manuscriptRoles;
    /**
     * Array containing all manuscriptroles inherited via occurrences
     * Structure:
     *  [
     *      role_system_name => [
     *          role,
     *          [
     *              manuscript_id => [
     *                  manuscript, occurrence, occurrence, ...
     *              ],
     *              manuscript_id => [
     *                  manuscript, occurrence, occurrence, ...
     *              ],
     *          ],
     *      ],
     *      role_system_name => [...],
     *  ]
     * @var array
     */
    private $occurrenceManuscriptRoles;
    // TODO: occurrences, types
    // TODO: articles, books, bookChapters

    public function __construct()
    {
        parent::__construct();

        $this->offices = [];
        $this->manuscriptRoles = [];
        $this->occurrenceManuscriptRoles = [];

        return $this;
    }

    public function setFirstName(string $firstName = null): Person
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setLastName(string $lastName = null): Person
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setExtra(string $extra = null): Person
    {
        $this->extra = $extra;

        return $this;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setUnprocessed(string $unprocessed = null): Person
    {
        $this->unprocessed = $unprocessed;

        return $this;
    }

    public function getUnprocessed(): ?string
    {
        return $this->unprocessed;
    }

    public function setBornDate(FuzzyDate $bornDate = null): Person
    {
        $this->bornDate = $bornDate;

        return $this;
    }

    public function getBornDate(): ?FuzzyDate
    {
        return $this->bornDate;
    }

    public function setDeathDate(FuzzyDate $deathDate = null): Person
    {
        $this->deathDate = $deathDate;

        return $this;
    }

    public function getDeathDate(): ?FuzzyDate
    {
        return $this->deathDate;
    }

    public function addRole(Role $role): Person
    {
        $this->roles[$role->getId()] = $role;

        return $this;
    }

    public function addOffice(Office $office): Person
    {
        $this->offices[$office->getId()] = $office;

        return $this;
    }

    public function getOffices(): array
    {
        return $this->offices;
    }

    public function setHistorical(bool $historical = null): Person
    {
        $this->historical = empty($historical) ? false : $historical;

        return $this;
    }

    public function getHistorical(): bool
    {
        return $this->historical;
    }

    public function setModern(bool $modern = null): Person
    {
        $this->modern = empty($modern) ? false : $modern;

        return $this;
    }

    public function getModern(): bool
    {
        return $this->modern;
    }

    public function addManuscriptRole(Role $role, Manuscript $manuscript): Person
    {
        if (!isset($this->manuscriptRoles[$role->getSystemName()])) {
            $this->manuscriptRoles[$role->getSystemName()] = [$role, []];
        }
        if (!isset($this->manuscriptRoles[$role->getSystemName()][1][$manuscript->getId()])) {
            $this->manuscriptRoles[$role->getSystemName()][1][$manuscript->getId()] = $manuscript;
        }

        return $this;
    }

    public function addOccurrenceManuscriptRole(Role $role, Manuscript $manuscript, Occurrence $occurrence): Person
    {
        if (!isset($this->occurrenceManuscriptRoles[$role->getSystemName()])) {
            $this->occurrenceManuscriptRoles[$role->getSystemName()] = [$role, []];
        }
        if (!isset($this->occurrenceManuscriptRoles[$role->getSystemName()][1][$manuscript->getId()])) {
            $this->occurrenceManuscriptRoles[$role->getSystemName()][1][$manuscript->getId()] = [$manuscript];
        }
        $this->occurrenceManuscriptRoles[$role->getSystemName()][1][$manuscript->getId()][] = $occurrence;

        return $this;
    }

    public function getAllManuscriptRoles(): array
    {
        $manuscriptRoles = $this->manuscriptRoles;
        foreach ($this->occurrenceManuscriptRoles as $roleName => $occurrenceManuscriptRole) {
            $role = array_shift($occurrenceManuscriptRole);
            if (!isset($manuscriptRoles[$roleName])) {
                $manuscriptRoles[$roleName] = [$role, []];
            }
            foreach ($occurrenceManuscriptRole[0] as $manuscriptId => $occurrenceManuscript) {
                if (!isset($manuscriptRoles[$roleName][$manuscriptId])) {
                    $manuscript = array_shift($occurrenceManuscript);
                    // if all occurrences linked to a manuscript are not public, indicate manuscript as not public
                    if ($manuscript->getPublic()) {
                        $public = false;
                        foreach ($occurrenceManuscript as $occurrence) {
                            if ($occurrence->getPublic()) {
                                $public = true;
                                break;
                            }
                        }
                        if (!$public) {
                            $manuscript->setPublic(false);
                        }
                    }
                    $manuscriptRoles[$roleName][1][$manuscriptId] = $manuscript;
                }
            }
        }
        return $manuscriptRoles;
    }

    public function getAllPublicManuscriptRoles(): array
    {
        $manuscriptRoles = $this->getAllManuscriptRoles();
        foreach ($manuscriptRoles as $roleName => $manuscriptRole) {
            foreach ($manuscriptRole[1] as $manuscriptId => $manuscript) {
                if (!$person->getPublic()) {
                    unset($manuscriptRoles[$roleName][1][$manuscriptId]);
                }
            }
            if (empty($manuscriptRoles[$roleName][1])) {
                unset($manuscriptRoles[$roleName]);
            }
        }
        return $manuscriptRoles;
    }

    public function getOnlyRelatedManuscripts(): array
    {
        if (!isset($this->manuscriptRoles['related'])) {
            return [];
        }
        $result = [];
        $relatedManuscripts = $this->manuscriptRoles['related'][1];
        $allManuscriptRoles = $this->getAllManuscriptRoles();
        foreach ($relatedManuscripts as $relatedManuscriptId => $relatedManuscript) {
            $found = false;
            foreach ($allManuscriptRoles as $roleName => $manuscriptrole) {
                if ($roleName == 'related') {
                    continue;
                }
                if (isset($manuscriptrole[1][$relatedManuscriptId])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $result[$relatedManuscriptId] = $relatedManuscript;
            }
        }
        return $result;
    }

    public function getOnlyRelatedPublicManuscripts(): array
    {
        if (!isset($this->manuscriptRoles['related'])) {
            return [];
        }
        $result = [];
        $relatedManuscripts = $this->manuscriptRoles['related'][1];
        $allManuscriptRoles = $this->getAllPublicManuscriptRoles();
        foreach ($relatedManuscripts as $relatedManuscriptId => $relatedManuscript) {
            if (!$relatedManuscript->getPublic()) {
                continue;
            }
            $found = false;
            foreach ($allManuscriptRoles as $roleName => $manuscriptrole) {
                if ($roleName == 'related') {
                    continue;
                }
                if (isset($manuscriptrole[1][$relatedManuscriptId])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $result[$relatedManuscriptId] = $relatedManuscript;
            }
        }
        return $result;
    }

    public function getFixedRelatedManuscriptRoles(): array
    {
        $allManuscriptRoles = $this->getAllManuscriptRoles();
        if (isset($allManuscriptRoles['related'])) {
            $relatedManuscriptRoles = $this->getOnlyRelatedManuscripts();
            if (empty($relatedManuscriptRoles)) {
                unset($allManuscriptRoles['related']);
            } else {
                $allManuscriptRoles['related'][1] = $relatedManuscriptRoles;
            }
        }
        return $allManuscriptRoles;
    }

    public function getFixedRelatedPublicManuscriptRoles(): array
    {
        $allManuscriptRoles = $this->getAllPublicManuscriptRoles();
        if (isset($allManuscriptRoles['related'])) {
            $relatedManuscriptRoles = $this->getOnlyRelatedPublicManuscripts();
            if (empty($relatedManuscriptRoles)) {
                unset($allManuscriptRoles['related']);
            } else {
                $allManuscriptRoles['related'][1] = $relatedManuscriptRoles;
            }
        }
        return $allManuscriptRoles;
    }

    public function getName(): string
    {
        $nameArray = array_filter([
            $this->firstName,
            $this->lastName,
            $this->extra,
        ]);
        if (empty($nameArray)) {
            return $this->unprocessed;
        }
        return implode(' ', $nameArray);
    }

    public function getInterval(): FuzzyInterval
    {
        return new FuzzyInterval($this->bornDate, $this->deathDate);
    }

    public function getFullDescription(): string
    {
        $nameArray = array_filter([
            $this->firstName,
            $this->lastName,
            $this->extra,
        ]);
        if (empty($nameArray)) {
            $description = $this->unprocessed;
        } else {
            $description = implode(' ', $nameArray);
            if (!$this->bornDate->isEmpty() && !$this->deathDate->isEmpty()) {
                $description .= ' (' . new FuzzyInterval($this->bornDate, $this->deathDate) . ')';
            }
        }
        foreach ($this->identifications as $identification) {
            if ($identification->getIdentifier()->getPrimary()) {
                $description .= ' - ' . $identification->getIdentifier()->getName() . ': ' . implode(', ', $identification->getIdentifications());
            }
        }

        return $description;
    }

    public function getFullDescriptionWithOffices(): string
    {
        $description = $this->getFullDescription();

        if (!empty($this->offices)) {
            $description .= ' (' . implode(', ', $this->offices) . ')';
        }

        return $description;
    }

    public function getShortDescription(): string
    {
        $nameArray = array_filter([
            isset($this->firstName) ? mb_substr($this->firstName, 0, 1) . '.' : null,
            $this->lastName,
            $this->extra,
        ]);
        if (empty($nameArray)) {
            return $this->unprocessed;
        }

        return implode(' ', $nameArray);
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getFullDescription(),
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        $result['name'] = $this->getFullDescriptionWithOffices();
        $result['historical'] = $this->historical;
        $result['modern'] = $this->modern;

        if (isset($this->firstName)) {
            $result['firstName'] = $this->firstName;
        }
        if (isset($this->lastName)) {
            $result['lastName'] = $this->lastName;
        }
        if (isset($this->extra)) {
            $result['extra'] = $this->extra;
        }
        if (isset($this->unprocessed)) {
            $result['unprocessed'] = $this->unprocessed;
        }
        if (isset($this->bornDate) && !($this->bornDate->isEmpty())) {
            $result['bornDate'] = $this->bornDate->getJson();
        }
        if (isset($this->deathDate) && !($this->deathDate->isEmpty())) {
            $result['deathDate'] = $this->deathDate->getJson();
        }
        if (!empty($this->offices)) {
            $result['office'] = ArrayToJson::arrayToShortJson($this->offices);
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['name'] = $this->getName();
        $result['historical'] = $this->historical;
        $result['modern'] = $this->modern;

        if (isset($this->bornDate) && !empty($this->bornDate->getFloor())) {
            $result['born_date_floor_year'] = intval($this->bornDate->getFloor()->format('Y'));
        }
        if (isset($this->bornDate) && !empty($this->bornDate->getCeiling())) {
            $result['born_date_ceiling_year'] = intval($this->bornDate->getCeiling()->format('Y'));
        }
        if (isset($this->deathDate) && !empty($this->deathDate->getFloor())) {
            $result['death_date_floor_year'] = intval($this->deathDate->getFloor()->format('Y'));
        }
        if (isset($this->deathDate) && !empty($this->deathDate->getCeiling())) {
            $result['death_date_ceiling_year'] = intval($this->deathDate->getCeiling()->format('Y'));
        }
        if (!empty($this->roles)) {
            $result['role'] = ArrayToJson::arrayToShortJson($this->roles);
        }
        if (!empty($this->offices)) {
            $result['office'] = ArrayToJson::arrayToShortJson($this->offices);
        }

        return $result;
    }

    public static function sortByFullDescription($a, $b)
    {
        return strcmp($a->getFullDescription(), $b->getFullDescription());
    }
}
