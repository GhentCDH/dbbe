<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

/**
 */
class Person extends Entity implements SubjectInterface
{
    /**
     * @var string
     */
    const CACHENAME = 'person';

    /**
     * @var string
     */
    protected $firstName;
    /**
     * @var string
     */
    protected $lastName;
    /**
     * @var array
     */
    protected $selfDesignations = [];
    /**
     * @var Origin
     */
    protected $origin;
    /**
     * @var string
     */
    protected $extra;
    /**
     * @var string
     */
    protected $unprocessed;
    /**
     * @var FuzzyDate
     */
    protected $bornDate;
    /**
     * @var FuzzyDate
     */
    protected $deathDate;
    /**
     * @var FuzzyDate
     */
    protected $unknownDate;
    /**
     * @var FuzzyInterval
     */
    protected $unknownInterval;
    /**
     * @var array
     */
    protected $officesWithParents = [];
    /**
     * @var bool
     */
    protected $historical;
    /**
     * @var bool
     */
    protected $modern;
    /**
     * @var array
     */
    protected $roles = [];
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
    protected $manuscriptRoles = [];
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
    protected $occurrenceManuscriptRoles = [];
    /**
     * Array containing all document roles (except for manuscripts)
     * Structure:
     *  [
     *      document_type => [
     *          role_system_name => [
     *              role,
     *              [
     *                  occurrence_id => occurrence,
     *                  occurrence_id => occurrence,
     *              ],
     *          ],
     *          role_system_name => [...],
     *      ],
     *      document_type => [...],
     *  ]
     * @var array
     */
    protected $documentRoles = [];

    /**
     * @param  string|null $firstName
     * @return Person
     */
    public function setFirstName(string $firstName = null): Person
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param  string|null $lastName
     * @return Person
     */
    public function setLastName(string $lastName = null): Person
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param  array $selfDesignations
     * @return Person
     */
    public function setSelfDesignations(array $selfDesignations): Person
    {
        $this->selfDesignations = $selfDesignations;

        return $this;
    }

    /**
     * @return array
     */
    public function getSelfDesignations(): array
    {
        return $this->selfDesignations;
    }

    /**
     * @param  Origin $origin
     * @return Person
     */
    public function setOrigin(Origin $origin): Person
    {
        $this->origin = $origin;

        return $this;
    }

    /**
     * @return Origin|null
     */
    public function getOrigin(): ?Origin
    {
        return $this->origin;
    }

    /**
     * @param  string|null $extra
     * @return Person
     */
    public function setExtra(string $extra = null): Person
    {
        $this->extra = $extra;

        return $this;
    }


    /**
     * @return string|null
     */
    public function getExtra(): ?string
    {
        return $this->extra;
    }

    /**
     * @param  string|null $unprocessed
     * @return Person
     */
    public function setUnprocessed(string $unprocessed = null): Person
    {
        $this->unprocessed = $unprocessed;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getUnprocessed(): ?string
    {
        return $this->unprocessed;
    }

    /**
     * @param  FuzzyDate|null $bornDate
     * @return Person
     */
    public function setBornDate(FuzzyDate $bornDate = null): Person
    {
        $this->bornDate = $bornDate;

        return $this;
    }

    /**
     * @return FuzzyDate|null
     */
    public function getBornDate(): ?FuzzyDate
    {
        return $this->bornDate;
    }

    /**
     * @param  FuzzyDate|null $deathDate
     * @return Person
     */
    public function setDeathDate(FuzzyDate $deathDate = null): Person
    {
        $this->deathDate = $deathDate;

        return $this;
    }

    /**
     * @return FuzzyDate|null
     */
    public function getDeathDate(): ?FuzzyDate
    {
        return $this->deathDate;
    }

    /**
     * @param  FuzzyDate|null $unknownDate
     * @return Person
     */
    public function setUnknownDate(FuzzyDate $unknownDate = null): Person
    {
        $this->unknownDate = $unknownDate;

        return $this;
    }

    /**
     * @return FuzzyDate|null
     */
    public function getUnknownDate(): ?FuzzyDate
    {
        return $this->unknownDate;
    }

    /**
     * @param  FuzzyInterval|null $unknownInterval
     * @return Person
     */
    public function setUnknownInterval(FuzzyInterval $unknownInterval = null): Person
    {
        $this->unknownInterval = $unknownInterval;

        return $this;
    }

    /**
     * @return FuzzyInterval|null
     */
    public function getUnknownInterval(): ?FuzzyInterval
    {
        return $this->unknownInterval;
    }

    /**
     * @param  array $roles
     * @return Person
     */
    public function setRoles(array $roles): Person
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @param  Role   $role
     * @return Person
     */
    public function addRole(Role $role): Person
    {
        $this->roles[$role->getId()] = $role;

        return $this;
    }

    /**
     * @param  array $officesWithParents
     * @return Person
     */
    public function setOfficesWithParents(array $officesWithParents): Person
    {
        $this->officesWithParents = $officesWithParents;

        return $this;
    }

    /**
     * @param  OfficeWithParents $officeWithParents
     * @return Person
     */
    public function addOfficeWithParents(OfficeWithParents $officeWithParents): Person
    {
        $this->officesWithParents[$officeWithParents->getId()] = $officeWithParents;

        return $this;
    }

    /**
     * @return array
     */
    public function getOfficesWithParents(): array
    {
        return $this->officesWithParents;
    }

    /**
     * @param bool|null $historical
     * @return Person
     */
    public function setHistorical(bool $historical = null): Person
    {
        $this->historical = empty($historical) ? false : $historical;

        return $this;
    }

    /**
     * @return bool
     */
    public function getHistorical(): bool
    {
        return $this->historical;
    }

    /**
     * @param bool|null $modern
     * @return Person
     */
    public function setModern(bool $modern = null): Person
    {
        $this->modern = empty($modern) ? false : $modern;

        return $this;
    }

    /**
     * @return bool
     */
    public function getModern(): bool
    {
        return $this->modern;
    }

    /**
     * @param  array $manuscriptRoles
     * @return Person
     */
    public function setManuscriptRoles(array $manuscriptRoles): Person
    {
        $this->manuscriptRoles = $manuscriptRoles;

        return $this;
    }

    /**
     * @param  Role       $role
     * @param  Manuscript $manuscript
     * @return Person
     */
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

    /**
     * @param  array $occurrenceManuscriptRoles
     * @return Person
     */
    public function setOccurrenceManuscriptRoles(array $occurrenceManuscriptRoles): Person
    {
        $this->occurrenceManuscriptRoles = $occurrenceManuscriptRoles;

        return $this;
    }

    /**
     * @param  Role       $role
     * @param  Manuscript $manuscript
     * @param  Occurrence $occurrence
     * @return Person
     */
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

    /**
     * @return array
     */
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

    /**
     * @return array
     */
    public function getAllPublicManuscriptRoles(): array
    {
        $manuscriptRoles = $this->getAllManuscriptRoles();
        foreach ($manuscriptRoles as $roleName => $manuscriptRole) {
            foreach ($manuscriptRole[1] as $manuscriptId => $manuscript) {
                if (!$manuscript->getPublic()) {
                    unset($manuscriptRoles[$roleName][1][$manuscriptId]);
                }
            }
            if (empty($manuscriptRoles[$roleName][1])) {
                unset($manuscriptRoles[$roleName]);
            }
        }
        return $manuscriptRoles;
    }

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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

    /**
     * @param  array $documentRoles
     * @return Person
     */
    public function setDocumentRoles(array $documentRoles): Person
    {
        $this->documentRoles = $documentRoles;

        return $this;
    }

    /**
     * @param  string   $documentType
     * @param  Role     $role
     * @param  Document $document
     * @return Person
     */
    public function addDocumentRole(string $documentType, Role $role, Document $document): Person
    {
        if (!isset($this->documentRoles[$documentType])) {
            $this->documentRoles[$documentType] = [];
        }
        if (!isset($this->documentRoles[$documentType][$role->getSystemName()])) {
            $this->documentRoles[$documentType][$role->getSystemName()] = [$role, []];
        }
        if (!isset($this->documentRoles[$documentType][$role->getSystemName()][1][$document->getId()])) {
            $this->documentRoles[$documentType][$role->getSystemName()][1][$document->getId()] = $document;
        }

        return $this;
    }

    /**
     * @param  string $documentType
     * @return array
     */
    public function getDocumentRoles(string $documentType): array
    {
        if (isset($this->documentRoles[$documentType])) {
            return $this->documentRoles[$documentType];
        }
        return [];
    }

    /**
     * @param  string $documentType
     * @return array
     */
    public function getPublicDocumentRoles(string $documentType): array
    {
        $documentRolesForType = $this->getDocumentRoles($documentType);
        foreach ($documentRolesForType as $roleName => $documentRole) {
            foreach ($documentRole[1] as $documentId => $document) {
                if (!$document->getPublic()) {
                    unset($documentRolesForType[$roleName][1][$documentId]);
                }
            }
            if (empty($documentRolesForType[$roleName][1])) {
                unset($documentRolesForType[$roleName]);
            }
        }
        return $documentRolesForType;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        $nameArray = array_filter([
            $this->firstName,
            $this->lastName,
            $this->origin ? ' of ' . $this->origin->getName() : null,
            $this->extra,
        ]);
        $nameArray = array_filter($nameArray);
        if (empty($nameArray)) {
            return $this->unprocessed;
        }
        return implode(' ', $nameArray);
    }

    /**
     * @return FuzzyInterval
     */
    public function getInterval(): FuzzyInterval
    {
        return new FuzzyInterval($this->bornDate, $this->deathDate);
    }

    /**
     * @return string
     */
    public function getFullDescription(): string
    {
        $nameArray = array_filter([
            $this->firstName,
            $this->lastName,
            $this->origin ? ' of ' . $this->origin->getName() : null,
            $this->extra,
        ]);
        $nameArray = array_filter($nameArray);
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
                $description .=
                    ' - ' .
                    $identification->getIdentifier()->getName() .
                    ': ' .
                    implode(', ', $identification->getIdentifications());
            }
        }

        return $description;
    }

    /**
     * @return string
     */
    public function getFullDescriptionWithOffices(): string
    {
        $description = $this->getFullDescription();

        if (!empty($this->officesWithParents)) {
            $description .= ' (' . implode(', ', $this->officesWithParents) . ')';
        }

        return $description;
    }

    /**
     * @return string
     */
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

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->getFullDescriptionWithOffices();
    }

    /**
     * @return array
     */
    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getFullDescription(),
        ];
    }

    /**
     * @return array
     */
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
        if (isset($this->selfDesignations)) {
            $result['selfDesignations'] = implode(', ', $this->selfDesignations);
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin->getShortJson();
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
        if (isset($this->unknownDate) && !($this->unknownDate->isEmpty())) {
            $result['unknownDate'] = $this->unknownDate->__toString();
        }
        if (isset($this->unknownInterval) && !($this->unknownInterval->isEmpty())) {
            $result['unknownInterval'] = $this->unknownInterval->__toString();
        }
        if (!empty($this->officesWithParents)) {
            $result['officesWithParents'] = ArrayToJson::arrayToShortJson($this->officesWithParents);
        }

        return $result;
    }

    /**
     * @return array
     */
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
        if (!empty($this->officesWithParents)) {
            $offices = [];
            foreach ($this->officesWithParents as $officeWithParents) {
                $offices = array_merge($offices, $officeWithParents->getShortElastic());
            }
            $result['office'] = $offices;
        }
        if (!empty($this->selfDesignations)) {
            $result['self_designation'] = $this->selfDesignations;
        }
        if (!empty($this->origin)) {
            $result['origin'] = $this->origin->getShortJson();
        }

        return $result;
    }
}
