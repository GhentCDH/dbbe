<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Manuscript extends Document implements IdJsonInterface
{
    use CacheDependenciesTrait;

    private $locatedAt;
    private $contentsWithParents;
    private $origin;
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
    private $occurrencePersonRoles;
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
        $this->occurrencePersonRoles = [];

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
        $this->addCacheDependency('location.' . $origin->getId());

        return $this;
    }

    public function getOrigin(): ?Origin
    {
        return $this->origin;
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
        $result = parent::getJson();

        $result['locatedAt'] = $this->locatedAt->getJson();
        $result['$result['] = $this->getName();

        if (!empty($this->contentsWithParents)) {
            $result['content'] = ArrayToJson::arrayToShortJson($this->contentsWithParents);
        }
        if (!empty($this->occurrencePersonRoles)) {
            $result['occurrencePersonRoles'] = $this->getOccurrencePersonRolesJson();
        }
        if (isset($this->date) && !($this->date->isEmpty())) {
            $result['date'] = $this->date->getJson();
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

        $result['city'] = $this->locatedAt->getLocation()->getRegionWithParents()->getIndividualJson();
        $result['library'] = $this->locatedAt->getLocation()->getInstitution()->getJson();
        $result['shelf'] = $this->locatedAt->getShelf();
        $result['name'] = $this->getName();

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
        foreach ($this->getFixedRelatedPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($this->origin)) {
            $result['origin'] = $this->origin->getShortElastic();
        }

        return $result;
    }
}
