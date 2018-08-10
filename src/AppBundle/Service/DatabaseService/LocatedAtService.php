<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class LocatedAtService extends DatabaseService
{
    public function getLocatedAtsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                -- iddocument is the unique identifier in the located_at table
                located_at.iddocument as locatedat_id,
                located_at.idlocation as location_id,
                located_at.identification as shelf,
                located_at.extra as extra
            from data.located_at
            where located_at.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function insert(int $documentId, int $locationId, string $shelf, string $extra = null): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.located_at (iddocument, idlocation, identification, extra)
            values (?, ?, ?, ?)',
            [
                $documentId,
                $locationId,
                $shelf,
                $extra
            ]
        );
    }

    public function updateLocation(int $locatedAtId, int $locationId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set idlocation = ?
            where located_at.iddocument = ?',
            [$locationId, $locatedAtId]
        );
    }

    public function updateShelf(int $locatedAtId, string $shelf): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set identification = ?
            where located_at.iddocument = ?',
            [$shelf, $locatedAtId]
        );
    }

    public function updateExtra(int $locatedAtId, string $extra): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set extra = ?
            where located_at.iddocument = ?',
            [$extra, $locatedAtId]
        );
    }
}
