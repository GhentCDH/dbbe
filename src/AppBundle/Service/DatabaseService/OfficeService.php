<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class OfficeService extends DatabaseService
{
    public function getOfficesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as office_id,
                occupation.occupation as name
            from data.occupation
            where occupation.idoccupation in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAllOffices(): array
    {
        return $this->conn->query(
            'SELECT
            occupation.idoccupation as office_id,
            occupation.occupation as name
            from data.occupation'
        )->fetchAll();
    }

    public function insert(string $name): int
    {
        $this->conn->executeUpdate(
            'INSERT INTO data.occupation (occupation)
            values (?)',
            [
                $name,
            ]
        );
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as office_id
            from data.occupation
            order by idoccupation desc
            limit 1'
        )->fetch()['occupation_id'];
    }

    public function updateName(int $officeId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.occupation
            set occupation = ?, modified = now()
            where occupation.idoccupation = ?',
            [$name, $officeId]
        );
    }

    public function delete(int $officeId): int
    {
        // don't delete if this occupation is used in person_occupation
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.person_occupation
            where person_occupation.idoccupation = ?',
            [$officeId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This office has dependencies.');
        }
        return $this->conn->executeUpdate(
            'DELETE from data.occupation
            where occupation.idoccupation = ?',
            [
                $officeId,
            ]
        );
    }
}
