<?php

namespace App\DatabaseService;

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
            'SELECT * from (
                SELECT
                    location.idlocation as location_id,
                    location.idregion as region_id,
                    null::integer as institution_id,
                    null as institution_name,
                    null::integer as collection_id,
                    null as collection_name
                from data.location
                where location.idregion is not null

                union

                select
                    location.idlocation as location_id,
                    institution.idregion as region_id,
                    institution.identity as institution_id,
                    institution.name as institution_name,
                    null::integer as collection_id,
                    null as collection_name
                from data.location
                inner join data.institution on location.idinstitution = institution.identity
                where location.idinstitution is not null

                union

                select
                    location.idlocation as location_id,
                    institution.idregion as region_id,
                    institution.identity as institution_id,
                    institution.name as institution_name,
                    fund.idfund as collection_id,
                    fund.name as collection_name
                from data.location
                inner join data.fund on location.idfund = fund.idfund
                inner join data.institution on fund.idlibrary = institution.identity
                where location.idfund is not null
            ) as locations
            where locations.location_id in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * Locations that can be used with locatedAt in a manuscript
     * * need an institution or fund
     * * institution needs to be a library
     * * need a region with a name (not historical name)
     * @return array
     */
    public function getLocationIdsForManuscripts(): array
    {
        return $this->conn->query(
            'SELECT
                location.idlocation as location_id
            from data.location
            left join data.fund on location.idfund = fund.idfund
            inner join data.institution on coalesce(location.idinstitution, fund.idlibrary) = institution.identity
            inner join data.library on institution.identity = library.identity
            inner join data.region on institution.idregion = region.identity
            and region.name is not null'
        )->fetchAll();
    }

    /**
     * Locations for editing locations (all regions have a location)
     * * institution needs to be a library
     * * need a region with a name (not historical name)
     * @return array
     */
    public function getLocationIdsForLocations(): array
    {
        return $this->conn->query(
            'SELECT
                location.idlocation as location_id
            from data.location
            left join data.fund on location.idfund = fund.idfund
            left join (
                select
                    institution.identity,
                    institution.idregion
                from data.institution
                inner join data.library on institution.identity = library.identity
            ) as instlib on coalesce(location.idinstitution, fund.idlibrary) = instlib.identity
            inner join data.region on coalesce(location.idregion, instlib.idregion) = region.identity
            and region.name is not null'
        )->fetchAll();
    }

    public function getLocationByRegion(int $regionId): int
    {
        return $this->conn->executeQuery(
            'SELECT
                location.idlocation as location_id
            from data.location
            where location.idregion = ?',
            [$regionId]
        )->fetchAll()[0]['location_id'];
    }
}
