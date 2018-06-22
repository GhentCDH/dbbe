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
}
