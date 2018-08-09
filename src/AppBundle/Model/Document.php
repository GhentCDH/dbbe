<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Document extends Entity
{
    protected $prevId;
    protected $date;
    /**
     * Array containing all personroles
     * Structure:
     *  [
     *      role_system_name => [
     *          role,
     *          [
     *              person_id => person,
     *              person_id => person,
     *          ],
     *      ],
     *      role_system_name => [...],
     *  ]
     * @var array
     */
    protected $personRoles;

    public function __construct()
    {
        parent::__construct();

        $this->personRoles = [];
        $this->bibliographies = [];

        return $this;
    }

    public function setPrevId(int $prevId): Occurrence
    {
        $this->prevId = $prevId;

        return $this;
    }

    public function getPrevId(): ?int
    {
        return $this->prevId;
    }

    public function setDate(FuzzyDate $date): Document
    {
        $this->date = $date;

        return $this;
    }

    public function getDate(): ?FuzzyDate
    {
        return $this->date;
    }
    public function addPersonRole(Role $role, Person $person): Document
    {
        if (!isset($this->personRoles[$role->getSystemName()])) {
            $this->personRoles[$role->getSystemName()] = [$role, []];
        }
        if (!isset($this->personRoles[$role->getSystemName()][1][$person->getId()])) {
            $this->personRoles[$role->getSystemName()][1][$person->getId()] = $person;
        }

        return $this;
    }

    public function getPersonRoles(): array
    {
        return $this->personRoles;
    }

    public function getPublicPersonRoles(): array
    {
        $personRoles = $this->personRoles;
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

    private function getPersonRolesJson(): array
    {
        $result = [];
        foreach ($this->personRoles as $roleName => $personRole) {
            $result[$roleName] = [];
            foreach ($personRole[1] as $person) {
                $result[$roleName][] = $person->getShortJson();
            }
        }
        return $result;
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        if (!empty($this->personRoles)) {
            $result['personRoles'] = $this->getPersonRolesJson();
        }
        if (!empty($this->getBibliographies())) {
            $result['bibliography'] = ArrayToJson::arrayToShortJson($this->getBibliographies());
        }

        return $result;
    }
}
