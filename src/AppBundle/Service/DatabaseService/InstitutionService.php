<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class InstitutionService extends DatabaseService
{
    public function getInstitutionsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                institution.identity as institution_id,
                institution.name
            from data.institution
            where institution.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getInstitutionsByRegion(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                institution.identity as institution_id
            from data.institution
            where institution.idregion = ?',
            [$regionId]
        )->fetchAll();
    }

    public function insert(string $name, int $regionId, bool $library = false, bool $monastery = false): int
    {
        // Set search_path for trigger ensure_institution_has_location
        $this->conn->exec('SET SEARCH_PATH TO data');
        $this->conn->executeUpdate(
            'INSERT INTO data.institution (name, idregion)
            values (?, ?)',
            [
                $name,
                $regionId,
            ]
        );
        $institutionId = $this->conn->executeQuery(
            'SELECT
                institution.identity as institution_id
            from data.institution
            order by identity desc
            limit 1'
        )->fetch()['institution_id'];
        if ($library) {
            $this->conn->executeUpdate(
                'INSERT INTO data.library (identity)
                values (?)',
                [
                    $institutionId,
                ]
            );
        }
        if ($monastery) {
            $this->conn->executeUpdate(
                'INSERT INTO data.monastery (identity)
                values (?)',
                [
                    $institutionId,
                ]
            );
        }
        return $institutionId;
    }

    public function updateName(int $institutionId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.institution
            set name = ?
            where institution.identity = ?',
            [$name, $institutionId]
        );
    }

    public function updateRegion(int $institutionId, int $regionId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.institution
            set idregion = ?
            where institution.identity = ?',
            [$regionId, $institutionId]
        );
    }

    public function delete(int $institutionId): int
    {
        // don't delete if this institution is used in fund
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.fund
            where fund.idlibrary = ?',
            [$institutionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This institution has dependencies.');
        }
        // don't delete if this institution is used in located_at
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.located_at
            inner join data.location on located_at.idlocation = location.idlocation
            where location.idinstitution = ?',
            [$institutionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This institution has dependencies.');
        }
        // don't delete if this institution is used in factoid
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.factoid
            inner join data.location on factoid.idlocation = location.idlocation
            where location.idinstitution = ?',
            [$institutionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This institution has dependencies.');
        }
        // Set search_path for trigger delete_entity
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.institution
            where institution.identity = ?',
            [
                $institutionId,
            ]
        );
    }
}
