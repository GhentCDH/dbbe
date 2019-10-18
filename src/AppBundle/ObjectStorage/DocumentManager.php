<?php

namespace AppBundle\ObjectStorage;

use DateTime;
use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Model\Document;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Role;

abstract class DocumentManager extends ObjectEntityManager
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

    public function getAcknowledgementDependencies(int $acknowledgementId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByAcknowledgementId($acknowledgementId), $method);
    }

    protected function setDates(array &$documents): void
    {
        $rawCompletionDates = $this->dbs->getCompletionDates(array_keys($documents));
        foreach ($rawCompletionDates as $rawCompletionDate) {
            $documents[$rawCompletionDate['document_id']]
                ->setDate(new FuzzyDate($rawCompletionDate['completion_date']));
        }
    }

    protected function setPrevIds(array &$documents): void
    {
        $rawPrevIds = $this->dbs->getPrevIds(array_keys($documents));
        foreach ($rawPrevIds as $rawPrevId) {
            $documents[$rawPrevId['document_id']]
                ->setPrevId($rawPrevId['prev_id']);
        }
    }

    protected function setPersonRoles(array &$documents): void
    {
        $rawRoles = $this->dbs->getPersonRoles(array_keys($documents));
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

    protected function setContributorRoles(array &$documents): void
    {
        $rawRoles = $this->dbs->getContributorRoles(array_keys($documents));
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
                    ->addContributorRole(
                        $roles[$raw['role_id']],
                        $persons[$raw['person_id']]
                    );
            }
        }
        foreach (array_keys($documents) as $documentId) {
            $documents[$documentId]->sortContributorRoles();
        }
    }

    protected function setAcknowledgements(array &$documents)
    {
        $rawAcknowledgements = $this->dbs->getAcknowledgements(array_keys($documents));
        $acknowledgements = $this->container->get('acknowledgement_manager')->getWithData($rawAcknowledgements);
        foreach ($rawAcknowledgements as $rawAcknowledgement) {
            $documents[$rawAcknowledgement['document_id']]
                ->addAcknowledgement($acknowledgements[$rawAcknowledgement['acknowledgement_id']]);
        }
        foreach (array_keys($documents) as $documentId) {
            $documents[$documentId]->sortAcknowledgements();
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

            // Update rank if needed
            if ($role->getRank()) {
                $oldPersons = isset($personRoles[$role->getSystemName()]) ? $personRoles[$role->getSystemName()][1] : [];
                foreach ($persons as $index => $person) {
                    if (!isset($oldPersons[$index]) || $oldPersons[$index]->getId() != $person->id) {
                        $this->dbs->updatePersonRoleRank($document->getId(), $person->id, $index + 1);
                    }
                }
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
    }

    protected function updateContributorRole(Document $document, Role $role, array $persons): void
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

        $contributorRoles = $document->getContributorRoles();
        $oldPersons = isset($contributorRoles[$role->getSystemName()]) ? $contributorRoles[$role->getSystemName()][1] : [];

        list($delIds, $addIds) = self::calcDiff($persons, $oldPersons);

        $this->dbs->beginTransaction();
        try {
            if (count($delIds) > 0) {
                $this->dbs->delPersonRole($document->getId(), $role->getId(), $delIds);
            }
            foreach ($addIds as $addId) {
                $this->dbs->addPersonRole($document->getId(), $role->getId(), $addId);
            }

            // Update rank if needed
            if ($role->getRank()) {
                $oldPersons = isset($contributorRoles[$role->getSystemName()]) ? $contributorRoles[$role->getSystemName()][1] : [];
                foreach ($persons as $index => $person) {
                    if (!isset($oldPersons[$index]) || $oldPersons[$index]->getId() != $person->id) {
                        $this->dbs->updatePersonRoleRank($document->getId(), $person->id, $index + 1);
                    }
                }
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
    }

    protected function validateDates($dates): void
    {
        parent::validateDates($dates);

        $completedItems = array_filter($dates, function ($item) {return $item->type == 'completed';});
        if (count($completedItems) > 1) {
            throw new BadRequestHttpException('Too many completed dates (only one allowed).');
        }
        foreach ($completedItems as $completedItem) {
            if ($completedItem->isInterval) {
                throw new BadRequestHttpException('Only dates are allowed for completed dates.');
            }
        }
    }

    protected function updateDates(Document $document, array $dates): void
    {
        $completedItems = array_values(array_filter($dates, function ($item) {return $item->type == 'completed at';}));
        if ($document->getDate() == null && count($completedItems) != 0) {
            $this->dbs->insertDate($document->getId(), 'completed at', $this->getDBDate($completedItems[0]->date));
        } elseif ($document->getDate() != null && count($completedItems) != 0) {
            if ($document->getDate()->getFloor() != new DateTime($completedItems[0]->date->floor)
                || $document->getDate()->getCeiling() != new DateTime($completedItems[0]->date->ceiling)
            ) {
                $this->dbs->updateDate($document->getId(), 'completed at', $this->getDBDate($completedItems[0]->date));
            }
        } elseif ($document->getDate() != null && count($completedItems) == 0) {
            $this->dbs->deleteDateOrInterval($document->getId(), 'completed at');
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

    protected function updateAcknowledgements(Document $document, array $acknowledgements): void
    {
        foreach ($acknowledgements as $acknowledgement) {
            if (!is_object($acknowledgement)
                || !property_exists($acknowledgement, 'id')
                || !is_numeric($acknowledgement->id)
            ) {
                throw new BadRequestHttpException('Incorrect acknowledgement data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($acknowledgements, $document->getAcknowledgements());

        if (count($delIds) > 0) {
            $this->dbs->delAcknowledgements($document->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addAcknowledgement($document->getId(), $addId);
        }
    }
}
