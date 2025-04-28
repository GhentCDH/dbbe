<?php

namespace App\Model;

use Collator;

use App\Utils\ArrayToJson;

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
     * @var array
     */
    protected $acknowledgements = [];
    /**
     * @var FuzzyDate
     */
    protected $bornDate;
    /**
     * @var FuzzyDate
     */
    protected $deathDate;
    /**
     * @var array
     */
    protected $attestedDatesAndIntervals = [];
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
     * @var bool
     */
    protected $dbbe;
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
     * Array containing all manuscripts that have this person as content (or any of the parents of content)
     */
    protected $manuscriptContents = [];
    /**
     * @var string
     */
    protected $fullDescription = null;

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
     * @param  SelfDesignation $selfDesignation
     * @return Person
     */
    public function addSelfDesignation(SelfDesignation $selfDesignation): Person
    {
        $this->selfDesignations[] = $selfDesignation;

        return $this;
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

    public function sortSelfDesignations(): Person
    {
        $collator = new Collator('');
        usort($this->selfDesignations, function ($a, $b) use ($collator) {
            return $collator->compare(strtolower($a->getName()), strtolower($b->getName()));
        });

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


    public function getFormattedBornDate(): ?string
    {
        return $this->bornDate->getFormattedDate();
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

    public function getFormattedDeathDate(): ?string
    {
        return $this->deathDate->getFormattedDate();
    }

    /**
     * @param  FuzzyDate|FuzzyInterval $attestedDate
     * @return Person
     */
    public function addAttestedDateOrInterval($attested): Person
    {
        $this->attestedDatesAndIntervals[] = $attested;

        return $this;
    }

    /**
     * @return array
     */
    public function getAttestedDatesAndIntervals(): array
    {
        return $this->attestedDatesAndIntervals;
    }

    public function getFormattedAttestedDatesAndIntervals(): array {
        return array_map(fn($fuzzyDate) => $fuzzyDate->getFormattedDate(), $this->attestedDatesAndIntervals);
    }

    public function sortAttestedDatesAndIntervals(): Person
    {
        usort($this->attestedDatesAndIntervals, function ($a, $b) {
            return strcmp($a->getSortKey(), $b->getSortKey());
        });

        return $this;
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

    public function sortOfficesWithParents(): Person
    {
        usort($this->officesWithParents, function ($a, $b) {
            return strcmp(strtolower($a->getName()), strtolower($b->getName()));
        });

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
     * @param bool|null $dbbe
     * @return Person
     */
    public function setDBBE(bool $dbbe = null): Person
    {
        $this->dbbe = empty($dbbe) ? false : $dbbe;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDBBE(): bool
    {
        return $this->dbbe;
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
        uasort(
            $manuscriptRoles,
            function ($a, $b) {
                return $a[0]->getOrder() <=> $b[0]->getOrder();
            }
        );
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
        foreach (array_keys($allManuscriptRoles) as $role) {
            usort(
                $allManuscriptRoles[$role][1],
                function ($a, $b) {
                    return $a->getSortKey() <=> $b->getSortKey();
                }
            );
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
        foreach (array_keys($allManuscriptRoles) as $role) {
            usort(
                $allManuscriptRoles[$role][1],
                function ($a, $b) {
                    return $a->getSortKey() <=> $b->getSortKey();
                }
            );
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
            foreach (array_keys($this->documentRoles[$documentType]) as $role) {
                if (in_array($documentType, ['article', 'book', 'bookChapter'])) {
                    usort(
                        $this->documentRoles[$documentType][$role][1],
                        function ($a, $b) {
                            return $a->getSortKey() <=> $b->getSortKey();
                        }
                    );
                } else if (in_array($documentType, ['occurrence'])){
                    $collator = new Collator('el_GR');
                    usort(
                        $this->documentRoles[$documentType][$role][1],
                        function ($a, $b) use ($collator) {
                            $a_cleaned= trim(preg_replace('/[()\[\]…]|\.{3}/u', '', strtolower($a->getDescription())));
                            $b_cleaned= trim(preg_replace('/[()\[\]…]|\.{3}/u', '', strtolower($b->getDescription())));
                            return $collator->compare($a_cleaned, $b_cleaned);
                        }
                    );
                }
                else if (in_array($documentType, ['type'])){
                    $collator = new Collator('el_GR');
                    usort(
                        $this->documentRoles[$documentType][$role][1],
                        function ($a, $b) use ($collator) {
                            $a_cleaned= trim(preg_replace('/[()\[\]…]|\.{3}/u', '', strtolower($a->getDescription())));
                            $b_cleaned= trim(preg_replace('/[()\[\]…]|\.{3}/u', '', strtolower($b->getDescription())));
                            return $collator->compare($a_cleaned, $b_cleaned);
                        }
                    );
                }
                else {
                    usort(
                        $this->documentRoles[$documentType][$role][1],
                        function ($a, $b) {
                            return $a->getDescription() <=> $b->getDescription();
                        }
                    );
                }
            }
            uasort(
                $this->documentRoles[$documentType],
                function ($a, $b) {
                    return $a[0]->getOrder() <=> $b[0]->getOrder();
                }
            );
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
     * @param array $manuscriptContents
     * @return Person
     */
    public function setManuscriptContents(array $manuscriptContents): Person
    {
        $this->manuscriptContents = $manuscriptContents;

        return $this;
    }

    /**
     * @param Manuscript $manuscript
     * @return Person
     */
    public function addManuscriptContent(Manuscript $manuscript): Person
    {
        $this->manuscriptContents[$manuscript->getId()] = $manuscript;

        return $this;
    }

    public function getManuscriptContents(): array
    {
        $manuscripts = $this->manuscriptContents;
        usort(
            $manuscripts,
            function ($a, $b) {
                return $a->getSortKey() <=> $b->getSortKey();
            }
        );
        return $manuscripts;
    }

    public function getPublicManuscriptContents(): array
    {
        $manuscripts = $this->getManuscriptContents();
        foreach ($manuscripts as $manuscriptId => $manuscript) {
            if (!$manuscript->getPublic()) {
                unset($manuscripts[$manuscriptId]);
            }
        }
        return $manuscripts;
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
    public function getInterval(): ?string
    {
        if ($this->bornDate != null && $this->deathDate != null) {
            return (new FuzzyInterval($this->bornDate, $this->deathDate))->getFormattedInterval();
        }
        return null;
    }

    /**
     * @return string
     */
    public function getNameAndDate(): string
    {
        $description = $this->getName();
        # Add date only if unprocessed name was not used
        if (!empty(array_filter(array_filter([
            $this->firstName,
            $this->lastName,
            $this->origin ? ' of ' . $this->origin->getName() : null,
            $this->extra,
            ])))) {
            if ($this->bornDate != null && !$this->bornDate->isEmpty() && $this->deathDate != null && !$this->deathDate->isEmpty()) {
                $description .= ' (' . (new FuzzyInterval($this->bornDate, $this->deathDate))->getFormattedInterval() . ')';
            }
        }
        return $description;
    }

    /**
     * @return string
     */
    public function getFullDescription(): string
    {
        if ($this->fullDescription == null) {
            $description = $this->getNameAndDate();
            foreach ($this->identifications as $identifications) {
                if ($identifications[0]->getPrimary() && $identifications[1] && $identifications[0]->getSystemName() != 'pinakes_person') {
                    $description .=
                        ' - ' .
                        $identifications[0]->getName() .
                        ': ' .
                        implode(', ', $identifications[1]);
                }
            }

            $this->fullDescription = $description;
        }

        return $this->fullDescription;
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
            !empty($this->firstName) ? mb_substr($this->firstName, 0, 1) . '.' : null,
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
            'id_name' => $this->id . '_' . $this->getFullDescription(),
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
        $result['dbbe'] = $this->dbbe;
        $result['dates'] = [];

        if (isset($this->firstName)) {
            $result['firstName'] = $this->firstName;
        }
        if (isset($this->lastName)) {
            $result['lastName'] = $this->lastName;
        }
        if (!empty($this->selfDesignations)) {
            $result['selfDesignations'] = ArrayToJson::arrayToShortJson($this->selfDesignations);
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
            $result['dates'][] = [
                'type' => 'born',
                'isInterval' => false,
                'date' => $this->bornDate->getJson(),
            ];
        }
        if (isset($this->deathDate) && !($this->deathDate->isEmpty())) {
            $result['dates'][] = [
                'type' => 'died',
                'isInterval' => false,
                'date' => $this->deathDate->getJson(),
            ];
        }
        foreach ($this->attestedDatesAndIntervals as $attested) {
            if (get_class($attested) == 'App\Model\FuzzyDate') {
                $result['dates'][] = [
                    'type' => 'attested',
                    'isInterval' => false,
                    'date' => $attested->getJson(),
                ];
            } else {
                $result['dates'][] = [
                    'type' => 'attested',
                    'isInterval' => true,
                    'interval' => $attested->getJson(),
                ];
            }
        }
        if (!empty($this->officesWithParents)) {
            $result['officesWithParents'] = ArrayToJson::arrayToShortJson($this->officesWithParents);
        }

        if (!empty($this->acknowledgements)) {
            $result['acknowledgements'] = ArrayToJson::arrayToShortJson($this->acknowledgements);
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
        $result['dbbe'] = $this->dbbe;

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
            $result['self_designation'] = ArrayToJson::arrayToShortJson($this->selfDesignations);
        }
        if (!empty($this->origin)) {
            $result['origin'] = $this->origin->getShortElastic();
        }

        if (!empty($this->getAcknowledgements())) {
            $result['acknowledgement'] =  ArrayToJson::arrayToShortJson($this->acknowledgements);
        }

        return $result;
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

    public function addAcknowledgement(Acknowledgement $acknowledgement): Person
    {
        $this->acknowledgements[] = $acknowledgement;

        return $this;
    }


}
