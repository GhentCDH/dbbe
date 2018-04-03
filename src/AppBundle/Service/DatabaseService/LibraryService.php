<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class LibraryService extends DatabaseService
{
    public function getLibrariesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                institution.identity as library_id,
                institution.name
            from data.institution
            where institution.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function insert(string $name, int $regionId): int
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
        $libraryId = $this->conn->executeQuery(
            'SELECT
                institution.identity as library_id
            from data.institution
            order by identity desc
            limit 1'
        )->fetch()['library_id'];
        $this->conn->executeUpdate(
            'INSERT INTO data.library (identity)
            values (?)',
            [
                $libraryId,
            ]
        );
        return $libraryId;
    }

    public function updateName(int $libraryId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.institution
            set name = ?
            where institution.identity = ?',
            [$name, $libraryId]
        );
    }

    public function delete(int $libraryId): int
    {
        // don't delete if this library is used in fund
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.fund
            where fund.idlibrary = ?',
            [$libraryId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This library has dependencies.');
        }
        // don't delete if this library is used in located_at
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.located_at
            inner join data.location on located_at.idlocation = location.idlocation
            where location.idinstitution = ?',
            [$libraryId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This library has dependencies.');
        }
        // Set search_path for trigger delete_entity
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.institution
            where institution.identity = ?',
            [
                $libraryId,
            ]
        );
    }
}
