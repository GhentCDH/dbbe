<?php

namespace AppBundle\Service\DatabaseService;

use DateTime;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class CollectionService extends DatabaseService
{
    public function getCollectionsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                fund.idfund as collection_id,
                fund.name
            from data.fund
            where fund.idfund in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function insert(string $name, int $libraryId): int
    {
        // Set search_path for trigger ensure_fund_has_location
        $this->conn->exec('SET SEARCH_PATH TO data');
        $this->conn->executeUpdate(
            'INSERT INTO data.fund (name, created, idlibrary)
            values (?, ?, ?)',
            [
                $name,
                new DateTime(),
                $libraryId,
            ],
            [
                \PDO::PARAM_STR,
                'datetime',
                \PDO::PARAM_INT,
            ]
        );
        return $this->conn->executeQuery(
            'SELECT
                fund.idfund as collection_id
            from data.fund
            order by idfund desc
            limit 1'
        )->fetch()['collection_id'];
    }

    public function updateName(int $collectionId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.fund
            set name = ?, modified = ?
            where fund.idfund = ?',
            [
                $name,
                new DateTime(),
                $collectionId,
            ],
            [
                \PDO::PARAM_STR,
                'datetime',
                \PDO::PARAM_INT,
            ]
        );
    }

    public function updateLibrary(int $collectionId, int $libraryId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.fund
            set idlibrary = ?, modified = ?
            where fund.idfund = ?',
            [
                $libraryId,
                new DateTime(),
                $collectionId,
            ],
            [
                \PDO::PARAM_INT,
                'datetime',
                \PDO::PARAM_INT,
            ]
        );
    }

    public function delete(int $collectionId): int
    {
        // don't delete if this collection is used in located_at
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.located_at
            inner join data.location on located_at.idlocation = location.idlocation
            where location.idfund = ?',
            [$collectionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This collection has dependencies.');
        }
        return $this->conn->executeUpdate(
            'DELETE from data.fund
            where fund.idfund = ?',
            [
                $collectionId,
            ]
        );
    }
}
