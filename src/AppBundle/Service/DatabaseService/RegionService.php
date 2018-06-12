<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class RegionService extends DatabaseService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                region.identity as region_id
            from data.region'
        )->fetchAll();
    }

    public function getRegionsWithParentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (identity, ids, names, historical_names, is_cities, pleiades_ids, depth) AS (
                SELECT
                    r.identity,
                    r.identity::text AS ids,
                    COALESCE(r.name, \'\') AS names,
                    COALESCE(r.historical_name, \'\') AS historical_names,
                    COALESCE(r.is_city::text, \'\') AS is_cities,
                    COALESCE(p.identifier, \'\') AS pleiades_ids,
                    1
                FROM data.region r
                left join (
                    select global_id.idsubject, global_id.identifier
                    from data.global_id
                    inner join data.institution
                        on global_id.idauthority = institution.identity
                        and institution.name = \'Pleiades\'
                ) as p on r.identity = p.idsubject

                UNION ALL

                SELECT
                    r.identity,
                    rec.ids || \':\' || r.identity::text AS ids,
                    rec.names || \':\' || COALESCE(r.name, \'\') AS names,
                    rec.historical_names || \':\' || COALESCE(r.historical_name, \'\') AS historical_names,
                    rec.is_cities || \':\' || COALESCE(r.is_city::text, \'\') AS is_cities,
                    rec.pleiades_ids || \':\' || COALESCE(p.identifier, \'\') AS pleiades_ids,
                    rec.depth + 1

                FROM rec
                INNER JOIN data.region r ON rec.identity = r.parent_idregion
                left join (
                    select global_id.idsubject, global_id.identifier
                    from data.global_id
                    inner join data.institution
                        on global_id.idauthority = institution.identity
                        and institution.name = \'Pleiades\'
                ) as p on r.identity = p.idsubject

            )
            SELECT rec.identity, ids, names, historical_names, is_cities, pleiades_ids
            FROM rec
            INNER JOIN (
                SELECT identity, MAX(depth) AS maxdepth
                FROM rec
                GROUP BY identity
            ) rj
            ON rec.identity = rj.identity AND rec.depth = rj.maxdepth
            WHERE rec.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getRegionsByRegion(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                region.identity as region_id
            from data.region
            where region.parent_idregion = ?',
            [$regionId]
        )->fetchAll();
    }

    public function insert(
        int $parentId = null,
        string $name,
        string $historicalName = null,
        bool $isCity = false
    ): int {
        // Set search_path for trigger ensure_entity_presence
        $this->conn->exec('SET SEARCH_PATH TO data');
        $this->conn->executeUpdate(
            'INSERT INTO data.region (parent_idregion, name, historical_name, is_city)
            values (?, ?, ?, ?)',
            [
                $parentId,
                $name,
                $historicalName,
                $isCity ? 'TRUE': 'FALSE',
            ]
        );
        $regionId = $this->conn->executeQuery(
            'SELECT
                region.identity as region_id
            from data.region
            order by identity desc
            limit 1'
        )->fetch()['region_id'];
        return $regionId;
    }

    public function updateParent(int $regionId, int $parentId = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set parent_idregion = ?
            where region.identity = ?',
            [
                $parentId,
                $regionId,
            ]
        );
    }

    public function updateName(int $regionId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set name = ?
            where region.identity = ?',
            [
                $name,
                $regionId,
            ]
        );
    }

    public function updateHistoricalName(int $regionId, string $historicalName): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set historical_name = ?
            where region.identity = ?',
            [
                $historicalName,
                $regionId,
            ]
        );
    }

    public function upsertPleiades(int $regionId, int $pleiades): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.global_id (idauthority, idsubject, identifier)
            values (
                (
                    select institution.identity
                    from data.institution
                    where institution.name = \'Pleiades\'
                ),
                ?,
                ?
            )
            -- primary key constraint on idauthority, idsubject
            on conflict (idauthority, idsubject) do update
            set identifier = excluded.identifier',
            [
                $regionId,
                $pleiades,
            ]
        );
    }

    public function updateIsCity(int $regionId, bool $isCity): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set is_city = ?
            where region.identity = ?',
            [
                $isCity ? 'TRUE': 'FALSE',
                $regionId,
            ]
        );
    }

    public function delete(int $regionId): int
    {
        // don't delete if this region is used in located_at
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.located_at
            inner join data.location on located_at.idlocation = location.idlocation
            where location.idregion = ?',
            [$regionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This region has located_at dependencies.');
        }
        // don't delete if this region is used in factoid
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.factoid
            inner join data.location on factoid.idlocation = location.idlocation
            where location.idregion = ?',
            [$regionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This region has factoid dependencies.');
        }
        // don't delete if this region is used in institution
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.institution
            where institution.idregion = ?',
            [$regionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This region has institution dependencies.');
        }
        // don't delete if this region is used in region (as parent)
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.region
            where region.parent_idregion = ?',
            [$regionId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This region has region dependencies.');
        }
        // Set search_path for trigger delete_entity
        // Pleiades id is deleted by foreign key constraint
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.region
            where region.identity = ?',
            [$regionId]
        );
    }
}
