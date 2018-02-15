<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class LocationService extends DatabaseService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                -- iddocument is the unique identifier in the located_at table
                located_at.iddocument as location_id
            from data.located_at'
        )->fetchAll();
    }

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

    public function getAllCitiesLibrariesCollections(): array
    {
        return $this->conn->query(
            '(
                SELECT
                    region.identity AS city_id,
                    region.name AS city_name,
                    institution.identity AS library_id,
                    institution.name AS library_name,
                    fund.idfund AS collection_id,
                    fund.name AS collection_name
                FROM data.location
                INNER JOIN data.fund ON location.idfund = fund.idfund
                INNER JOIN data.institution ON fund.idlibrary = institution.identity
                INNER JOIN data.region ON institution.idregion = region.identity

                UNION

                SELECT
                    region.identity AS city_id,
                    region.name AS city_name,
                    institution.identity AS library_id,
                    institution.name AS library_name,
                    NULL AS collection_id,
                    NULL AS collection_name
                FROM data.location
                INNER JOIN data.institution ON location.idinstitution = institution.identity
                INNER JOIN data.region ON institution.idregion = region.identity
                WHERE location.idfund is NULL
            )
            ORDER BY city_name, library_name, collection_name'
        )->fetchAll();
    }

    public function getAllOrigins(): array
    {
        return $this->conn->query(
            'SELECT
                location.idlocation as origin_id,
                coalesce(institution.idregion, location.idregion) as region_id,
                institution.identity as institution_id,
                institution.name as institution_name
            from data.location
            left join data.institution on location.idinstitution = institution.identity
            inner join data.region on coalesce(institution.idregion, location.idregion) = region.identity
            where region.historical_name is not null
            order by region.historical_name, institution.name'
        )->fetchAll();
    }

    public function updateLibraryId(int $documentId, int $libraryId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set idlocation = (select location.idlocation from data.location where location.idinstitution = ?)
            where located_at.iddocument = ?',
            [$libraryId, $documentId]
        );
    }

    public function updateCollectionId(int $documentId, int $collectionId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set idlocation = (select location.idlocation from data.location where location.idfund = ?)
            where located_at.iddocument = ?',
            [$collectionId, $documentId]
        );
    }

    public function updateShelf(int $documentId, string $shelf): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.located_at
            set identification = ?
            where located_at.iddocument = ?',
            [$shelf, $documentId]
        );
    }
}
