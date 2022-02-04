<?php

namespace App\Model;

use App\Utils\ArrayToJson;

class Manuscript extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'manuscript';

    /**
     * @var LocatedAt
     */
    protected $locatedAt;
    /**
     * @var array
     */
    protected $contentsWithParents = [];
    /**
     * @var Origin
     */
    protected $origin;
    /**
     * Array containing all personroles inherited via occurrences
     * Structure:
     *  [
     *      role_system_name => [
     *          role,
     *          [
     *              person_id => [
     *                  person, occurrence, occurrence, ...,
     *              ],
     *              person_id => [
     *                  person, occurrence, occurrence, ...,
     *              ],
     *          ],
     *      ],
     *      role_system_name => [...],
     *  ]
     * @var array
     */
    protected $occurrencePersonRoles = [];
    /**
     * Array of occurrences, in order
     * @var array
     */
    protected $occurrences = [];
    /**
     * @var Status
     */
    protected $status;
    /**
     * @var bool
     */
    protected $illustrated;

    public function setLocatedAt(LocatedAt $locatedAt): Manuscript
    {
        $this->locatedAt = $locatedAt;

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

    public function getDescription(): string
    {
        return $this->getName();
    }

    private function setContentsWithParents(array $contentsWithParents): Manuscript
    {
        $this->contentsWithParents = $contentsWithParents;

        return $this;
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

    public function setOccurrencePersonRoles(array $occurrencePersonRoles): Manuscript
    {
        $this->occurrencePersonRoles = $occurrencePersonRoles;

        return $this;
    }

    public function addOccurrencePersonRole(Role $role, Person $person, Occurrence $occurrence): Manuscript
    {
        if (!isset($this->occurrencePersonRoles[$role->getSystemName()])) {
            $this->occurrencePersonRoles[$role->getSystemName()] = [$role, []];
        }
        if (!isset($this->occurrencePersonRoles[$role->getSystemName()][1][$person->getId()])) {
            $this->occurrencePersonRoles[$role->getSystemName()][1][$person->getId()] = [$person];
        }
        $this->occurrencePersonRoles[$role->getSystemName()][1][$person->getId()][] = $occurrence;

        return $this;
    }

    private function getOccurrencePersonRolesJson(): array
    {
        $result = [];
        foreach ($this->occurrencePersonRoles as $roleName => $occurrencePersonRole) {
            $result[$roleName] = [];
            foreach ($occurrencePersonRole[1] as $occurrencePerson) {
                $person = array_shift($occurrencePerson);
                $row = $person->getShortJson();
                $row['occurrences'] = array_map(
                    function ($occurrence) {
                        return $occurrence->getDescription();
                    },
                    $occurrencePerson
                );
                $result[$roleName][] = $row;
            }
        }
        return $result;
    }

    public function getAllPersonRoles(): array
    {
        $personRoles = $this->personRoles;
        foreach ($this->occurrencePersonRoles as $roleName => $occurrencePersonRole) {
            $role = array_shift($occurrencePersonRole);
            if (!isset($personRoles[$roleName])) {
                $personRoles[$roleName] = [$role, []];
            }
            foreach ($occurrencePersonRole[0] as $personId => $occurrencePerson) {
                if (!isset($personRoles[$roleName][$personId])) {
                    $person = array_shift($occurrencePerson);
                    // if all occurrences linked to a person are not public, indicate person as not public
                    if ($person->getPublic()) {
                        $public = false;
                        foreach ($occurrencePerson as $occurrence) {
                            if ($occurrence->getPublic()) {
                                $public = true;
                                break;
                            }
                        }
                        if (!$public) {
                            $person->setPublic(false);
                        }
                    }
                    $personRoles[$roleName][1][$personId] = $person;
                }
            }
        }
        return $personRoles;
    }

    public function getAllPublicPersonRoles(): array
    {
        $personRoles = $this->getAllPersonRoles();
        foreach ($personRoles as $roleName => $personRole) {
            foreach ($personRole[1] as $personId => $person) {
                if (!$person->getPublic()) {
                    unset($personRoles[$roleName][1][$personId]);
                }
            }
            if (empty($personRoles[$roleName][1])) {
                unset($personRoles[$roleName]);
            }
        }
        return $personRoles;
    }

    public function getOnlyRelatedPersons(): array
    {
        if (!isset($this->personRoles['related'])) {
            return [];
        }
        $result = [];
        $relatedPersons = $this->personRoles['related'][1];
        $allPersonRoles = $this->getAllPersonRoles();
        foreach ($relatedPersons as $relatedPersonId => $relatedPerson) {
            $found = false;
            foreach ($allPersonRoles as $roleName => $personRole) {
                if ($roleName == 'related') {
                    continue;
                }
                if (isset($personRole[1][$relatedPersonId])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $result[$relatedPersonId] = $relatedPerson;
            }
        }
        return $result;
    }

    public function getOnlyRelatedPublicPersons(): array
    {
        if (!isset($this->personRoles['related'])) {
            return [];
        }
        $result = [];
        $relatedPersons = $this->personRoles['related'][1];
        $allPersonRoles = $this->getAllPublicPersonRoles();
        foreach ($relatedPersons as $relatedPersonId => $relatedPerson) {
            if (!$relatedPerson->getPublic()) {
                continue;
            }
            $found = false;
            foreach ($allPersonRoles as $roleName => $personRole) {
                if ($roleName == 'related') {
                    continue;
                }
                if (isset($personRole[1][$relatedPersonId])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $result[$relatedPersonId] = $relatedPerson;
            }
        }
        return $result;
    }

    /**
     * Person roles, related persons only contain persons that have no other roles
     * @return array [description]
     */
    public function getFixedRelatedPersonRoles(): array
    {
        $allPersonRoles = $this->getAllPersonRoles();
        if (isset($allPersonRoles['related'])) {
            $relatedPersonRoles = $this->getOnlyRelatedPersons();
            if (empty($relatedPersonRoles)) {
                unset($allPersonRoles['related']);
            } else {
                $allPersonRoles['related'][1] = $relatedPersonRoles;
            }
        }
        return $allPersonRoles;
    }

    /**
     * Person roles, related persons only contain persons that have no other roles
     * @return array [description]
     */
    public function getFixedRelatedPublicPersonRoles(): array
    {
        $allPersonRoles = $this->getAllPublicPersonRoles();
        if (isset($allPersonRoles['related'])) {
            $relatedPersonRoles = $this->getOnlyRelatedPublicPersons();
            if (empty($relatedPersonRoles)) {
                unset($allPersonRoles['related']);
            } else {
                $allPersonRoles['related'][1] = $relatedPersonRoles;
            }
        }
        return $allPersonRoles;
    }

    public function setOrigin(Origin $origin): Manuscript
    {
        $this->origin = $origin;

        return $this;
    }

    public function getOrigin(): ?Origin
    {
        return $this->origin;
    }

    public function setOccurrences(array $occurrences): Manuscript
    {
        $this->occurrences = $occurrences;

        return $this;
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

    public function getSortKey(): string
    {
        $nameParts = [];
        preg_match_all('/([^\d]+|[\d]+)/', $this->getName(), $nameParts);
        foreach ($nameParts[0] as $index => $namePart) {
            if (is_numeric($namePart)) {
                $nameParts[0][$index] = str_pad($namePart, 10, '0', STR_PAD_LEFT);
            }
        }
        return ''.join($nameParts[0]);
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
        $result = parent::getJson();

        $result['locatedAt'] = $this->locatedAt->getJson();
        $result['$result['] = $this->getName();

        if (!empty($this->contentsWithParents)) {
            $result['contents'] = ArrayToJson::arrayToShortJson($this->contentsWithParents);
        }
        if (!empty($this->occurrencePersonRoles)) {
            $result['occurrencePersonRoles'] = $this->getOccurrencePersonRolesJson();
        }
        $result['dates'] = [];
        if (!empty($this->date) && !($this->date->isEmpty())) {
            $result['dates'][] = [
                'type' => 'completed at',
                'isInterval' => false,
                'date' => $this->date->getJson(),
            ];
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin->getShortJson();
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
        $result = parent::getElastic();

        $result['city'] = $this->locatedAt->getLocation()->getRegionWithParents()->getIndividualShortJson();
        $result['library'] = $this->locatedAt->getLocation()->getInstitution()->getJson();
        $result['shelf'] = $this->locatedAt->getShelf();
        $result['name'] = $this->getName();

        if ($this->locatedAt->getLocation()->getCollection() != null) {
            $result['collection'] = $this->locatedAt->getLocation()->getCollection()->getJson();
        }
        if (!empty($this->contentsWithParents)) {
            $contents = [];
            $personContents = [];
            $personContentsPublic = [];
            $personContentIds = [];
            foreach ($this->contentsWithParents as $contentWithParents) {
                $contents = array_merge($contents, $contentWithParents->getShortElastic());
                foreach ($contentWithParents->getArray() as $content) {
                    if ($content->getPerson() != null && !in_array($content->getPerson()->getId(), $personContentIds)) {
                        $personContentIds[] = $content->getPerson()->getId();
                        $personContents[] = $content->getPerson()->getShortJson();
                        if ($content->getPerson()->getPublic()) {
                            $personContentsPublic[] = $content->getPerson()->getShortJson();
                        }
                    }
                }
            }
            $result['content'] = $contents;
            $result['person_content'] = $personContents;
            $result['person_content_public'] = $personContentsPublic;
        }
        if (!empty($this->date) && !empty($this->date->getFloor())) {
            $result['date_floor_year'] = intval($this->date->getFloor()->format('Y'));
        }
        if (!empty($this->date) && !empty($this->date->getCeiling())) {
            $result['date_ceiling_year'] = intval($this->date->getCeiling()->format('Y'));
        }
        foreach ($this->getFixedRelatedPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        foreach ($this->getFixedRelatedPublicPersonRoles() as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (!empty($this->origin)) {
            $result['origin'] = $this->origin->getShortElastic();
        }

        $result['number_of_occurrences'] = count($this->occurrences);

        return $result;
    }
}
