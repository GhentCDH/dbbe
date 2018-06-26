<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class OccupationService extends DatabaseService
{
    public function getOccupationsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as occupation_id,
                occupation.occupation as name,
                occupation.is_function
            from data.occupation
            where occupation.idoccupation in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAllOccupations(): array
    {
        return $this->conn->query(
            'SELECT
            occupation.idoccupation as occupation_id,
            occupation.occupation as name,
            occupation.is_function
            from data.occupation'
        )->fetchAll();
    }

    public function insert(string $name, bool $isFunction): int
    {
        $this->conn->executeUpdate(
            'INSERT INTO data.occupation (occupation, is_function)
            values (?, ?)',
            [
                $name,
                $isFunction ? 'TRUE': 'FALSE',
            ]
        );
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as occupation_id
            from data.occupation
            order by idoccupation desc
            limit 1'
        )->fetch()['occupation_id'];
    }

    public function updateName(int $occupationId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.occupation
            set occupation = ?
            where occupation.idoccupation = ?',
            [$name, $occupationId]
        );
    }

    public function delete(int $occupationId): int
    {
        // don't delete if this occupation is used in person_occupation
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.person_occupation
            where person_occupation.idoccupation = ?',
            [$occupationId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This occupation has dependencies.');
        }
        return $this->conn->executeUpdate(
            'DELETE from data.occupation
            where occupation.idoccupation = ?',
            [
                $occupationId,
            ]
        );
    }
}
