<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class LocationService extends DatabaseService
{
    public function getLocationsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                -- iddocument is the unique identifier in the located_at table
                located_at.iddocument as location_id,
                region.identity as city_id,
                region.name as city_name,
                institution.identity as library_id,
                institution.name as library_name,
                fund.idfund as collection_id,
                fund.name as collection_name,
                located_at.identification as shelf
            from data.located_at
            inner join data.location on located_at.idlocation = location.idlocation
            inner join data.fund on location.idfund = fund.idfund
            inner join data.institution on fund.idlibrary = institution.identity
            inner join data.region on institution.idregion = region.identity
            where located_at.iddocument in (?)
            union
            select
                -- iddocument is the unique identifier in the located_at table
                located_at.iddocument as location_id,
                region.identity as city_id,
                region.name as city_name,
                institution.identity as library_id,
                institution.name as library_name,
                null as collection_id,
                null as collection_name,
                located_at.identification as shelf
            from data.located_at
            inner join data.location on located_at.idlocation = location.idlocation
            inner join data.institution on location.idinstitution = institution.identity
            inner join data.region on institution.idregion = region.identity
            where located_at.iddocument in (?)
            and location.idfund is null',
            [$ids, $ids],
            [Connection::PARAM_INT_ARRAY, Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAllCities(): array
    {
        return $this->conn->query(
            'SELECT
                region.identity as city_id,
                region.name as city_name
            from data.region
            where region.is_city = TRUE'
        )->fetchAll();
    }

    public function getLibrariesInCity(int $city_id): array
    {
        return $this->conn->executeQuery(
            'SELECT
                institution.identity as library_id,
                institution.name as library_name
            from data.institution
            where institution.idregion = ?',
            [$city_id]
        )->fetchAll();
    }

    public function getCollectionsInLibrary(int $library_id): array
    {
        return $this->conn->executeQuery(
            'SELECT
                fund.idfund as collection_id,
                fund.name as collection_name
            from data.fund
            where fund.idlibrary = ?',
            [$library_id]
        )->fetchAll();
    }

    public function updateCollection(int $location_id, int $collection_id): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set idlocation = (select location.idlocation from data.location where location.idfund = ?)
            where located_at.iddocument = ?',
            [$collection_id, $location_id]
        );
    }

    public function updateLibrary(int $location_id, int $library_id): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set idlocation = (select location.idlocation from data.location where location.idinstitution = ?)
            where located_at.iddocument = ?',
            [$library_id, $location_id]
        );
    }

    public function updateShelf(int $document_id, string $shelf): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set identification = ?
            where located_at.iddocument = ?',
            [$shelf, $document_id]
        );
    }
}
