<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Model\Document;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Role;

class DocumentManager extends EntityManager
{
    public function getStatusDependencies(int $statusId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByStatusId($statusId), $method);
    }

    public function getPersonDependencies(int $personId, string $method = 'getId'): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $method);
    }

    public function getRoleDependencies(int $roleId, string $method = 'getMini'): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRoleId($roleId), $method);
    }

    protected function setDates(array &$documents): void
    {
        $rawCompletionDates = $this->dbs->getCompletionDates(self::getIds($documents));
        foreach ($rawCompletionDates as $rawCompletionDate) {
            $documents[$rawCompletionDate['document_id']]
                ->setDate(new FuzzyDate($rawCompletionDate['completion_date']));
        }
    }

    protected function setPrevIds(array &$documents): void
    {
        $rawPrevIds = $this->dbs->getPrevIds(self::getIds($documents));
        foreach ($rawPrevIds as $rawPrevId) {
            $documents[$rawPrevId['document_id']]
                ->setPrevId($rawPrevId['prev_id']);
        }
    }

    protected function setPersonRoles(array &$documents): void
    {
        $rawRoles = $this->dbs->getPersonRoles(self::getIds($documents));
        if (!empty($rawRoles)) {
            $personIds = self::getUniqueIds($rawRoles, 'person_id');

            $persons = [];
            if (count($personIds) > 0) {
                $persons = $this->container->get('person_manager')->getShort($personIds);
            }

            $roles = $this->container->get('role_manager')->getWithData($rawRoles);

            // Direct roles
            foreach ($rawRoles as $raw) {
                $documents[$raw['document_id']]
                    ->addPersonRole(
                        $roles[$raw['role_id']],
                        $persons[$raw['person_id']]
                    );
            }
        }
    }

    protected function updatePersonRole(Document $document, Role $role, array $persons): void
    {
        if (!is_array($persons)) {
            throw new BadRequestHttpException('Incorrect ' . $role->getSystemName() . ' data.');
        }
        foreach ($persons as $person) {
            if (!is_object($person)
                || (property_exists($person, 'id') && !is_numeric($person->id))
            ) {
                throw new BadRequestHttpException('Incorrect ' . $role->getSystemName() . ' data.');
            }
        }

        $personRoles = $document->getPersonRoles();
        $oldPersons = isset($personRoles[$role->getSystemName()]) ? $personRoles[$role->getSystemName()][1] : [];

        list($delIds, $addIds) = self::calcDiff($persons, $oldPersons);

        $this->dbs->beginTransaction();
        try {
            if (count($delIds) > 0) {
                $this->dbs->delPersonRole($document->getId(), $role->getId(), $delIds);
            }
            foreach ($addIds as $addId) {
                $this->dbs->addPersonRole($document->getId(), $role->getId(), $addId);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
    }

    protected function updatePersonRoleWithRank(Document $document, Role $role, array $persons): void
    {
        // First make sure all persons are saved
        $this->updatePersonRole($document, $role, $persons);

        // Update rank
        $oldPersons = isset($personRoles[$role->getSystemName()]) ? $personRoles[$role->getSystemName()][1] : [];
        foreach ($persons as $index => $person) {
            if (!isset($oldPersons[$index]) || $oldPersons[$index]->getId() != $person->id) {
                $this->dbs->updatePersonRoleRank($document->getId(), $person->id, $index + 1);
            }
        }
    }

    protected function updateStatus(Document $document, stdClass $status = null, string $statusType): void
    {
        if (empty($status)) {
            $this->dbs->deleteStatus($document->getId(), $statusType);
        } elseif (!property_exists($status, 'id')
            || !is_numeric($status->id)
        ) {
            throw new BadRequestHttpException('Incorrect record status data.');
        } else {
            $this->dbs->upsertStatus($document->getId(), $status->id, $statusType);
        }
    }
}
