<?php

namespace App\Model;

use App\Utils\ArrayToJson;

class Document extends Entity
{
    /**
     * @var string
     */
    protected $title;
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
    protected $personRoles = [];
    /**
     * Same structure as $personRoles
     * @var array
     */
    protected $contributorRoles = [];
    /**
     * @var array
     */
    protected $acknowledgements = [];

    /**
     * @param  string|null $title
     * @return Document
     */
    public function setTitle(string $title = null): Document
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getTitleSortKey(): ?string
    {
        return $this->title;
    }

    public function setPrevId(int $prevId): Document
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

    public function setPersonRoles(array $personRoles): Document
    {
        $this->personRoles = $personRoles;

        return $this;
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

    /**
     * Sort person roles alphabetically per role.
     */
    public function sortPersonRoles(): void
    {
        foreach ($this->personRoles as $roleName => $personRole) {
            // Don't sort some roles (https://github.ugent.be/idevos/DBBE-workflow/issues/453#issuecomment-125090)
            if (in_array($roleName, ['author', 'editor', 'translator', 'contributor', 'supervisor', 'transcriber'])) {
                continue;
            }
            uasort(
                $this->personRoles[$roleName][1],
                function ($a, $b) {
                    return $a->getFullDescriptionWithOffices() <=> $b->getFullDescriptionWithOffices();
                }
            );
        }
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

    public function setContributorRoles(array $contributorRoles): Document
    {
        $this->contributorRoles = $contributorRoles;

        return $this;
    }

    public function addContributorRole(Role $role, Person $person): Document
    {
        if (!isset($this->contributorRoles[$role->getSystemName()])) {
            $this->contributorRoles[$role->getSystemName()] = [$role, []];
        }
        if (!isset($this->contributorRoles[$role->getSystemName()][1][$person->getId()])) {
            $this->contributorRoles[$role->getSystemName()][1][$person->getId()] = $person;
        }

        return $this;
    }

    public function getContributorRoles(): array
    {
        return $this->contributorRoles;
    }

    public function getPublicContributorRoles(): array
    {
        $contributorRoles = $this->contributorRoles;
        foreach ($contributorRoles as $roleName => $contributorRole) {
            foreach ($contributorRole[1] as $personId => $person) {
                if (!$person->getPublic()) {
                    unset($contributorRoles[$roleName][1][$personId]);
                }
            }
            if (empty($contributorRoles[$roleName][1])) {
                unset($contributorRoles[$roleName]);
            }
        }
        return $contributorRoles;
    }

    /**
     * Sort contributor roles by role name and then alphabetically.
     * Order of role names: creator, transcriber, contributor.
     */
    public function sortContributorRoles(): void
    {
        $order = [
            'creator' => 0,
            'transcriber' => 1,
            'contributor' => 2,
        ];
        uksort(
            $this->contributorRoles,
            function ($a, $b) use ($order) {
                return $order[$a] - $order[$b];
            }
        );
        foreach ($this->contributorRoles as $roleName => $contributorRole) {
            uasort(
                $this->contributorRoles[$roleName][1],
                function ($a, $b) {
                    return $a->getFullDescriptionWithOffices() <=> $b->getFullDescriptionWithOffices();
                }
            );
        }
    }

    private function getContributorRolesJson(): array
    {
        $result = [];
        foreach ($this->contributorRoles as $roleName => $contributorRole) {
            $result[$roleName] = [];
            foreach ($contributorRole[1] as $person) {
                $result[$roleName][] = $person->getShortJson();
            }
        }
        return $result;
    }

    public function addAcknowledgement(Acknowledgement $acknowledgement): Document
    {
        $this->acknowledgements[] = $acknowledgement;

        return $this;
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

    public function getJson(): array
    {
        $result = parent::getJson();

        if (!empty($this->personRoles)) {
            $result['personRoles'] = $this->getPersonRolesJson();
        }

        if (!empty($this->contributorRoles)) {
            $result['contributorRoles'] = $this->getContributorRolesJson();
        }
        if (!empty($this->acknowledgements)) {
            $result['acknowledgements'] = ArrayToJson::arrayToShortJson($this->acknowledgements);
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['title_sort_key'] = $this->getTitleSortKey();

        foreach ($this->getContributorRoles() as $roleName => $contributorRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($contributorRole[1]);
        }
        foreach ($this->getPublicContributorRoles() as $roleName => $contributorRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($contributorRole[1]);
        }
        if (!empty($this->acknowledgements)) {
            $result['acknowledgement'] =  ArrayToJson::arrayToShortJson($this->acknowledgements);
        }

        return $result;
    }
}
