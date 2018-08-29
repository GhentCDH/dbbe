<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class RegionService extends DatabaseService
{
    /**
     * Get all region ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                region.identity as region_id
            from data.region'
        )->fetchAll();
    }

    /**
     * Get the ids of all childs of a specific region
     * @param  int $id
     * @return array
     */

    public function getChildIds(int $id): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (id, idparent) AS (
                SELECT
                    r.identity,
                    r.parent_idregion
                FROM data.region r

                UNION ALL

                SELECT
                    rec.id,
                    r.parent_idregion
                FROM rec
                INNER JOIN data.region r
                ON r.identity = rec.idparent
            )
            SELECT id as child_id
            FROM rec
            WHERE rec.idparent = ?',
            [$id]
        )->fetchAll();
    }

    /**
     * Get all ids of regions that are dependent on a specific region
     * @param  int   $regionId
     * @return array
     */
    public function getDepIdsByRegionId(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                region.identity as region_id
            from data.region
            where region.parent_idregion = ?',
            [$regionId]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getRegionsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                region.identity as region_id,
                region.name,
                region.historical_name,
                region.is_city,
                p.identifier as pleiades_id
            from data.region
            left join (
                select global_id.idsubject, global_id.identifier
                from data.global_id
                inner join data.institution
                on global_id.idauthority = institution.identity
                and institution.name = \'Pleiades\'
            ) as p on region.identity = p.idsubject
            where region.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getRegionsWithParentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (id, ids, names, historical_names, is_cities, pleiades_ids, depth) AS (
                SELECT
                    r.identity,
                    ARRAY[r.identity],
                    ARRAY[COALESCE(r.name, \'\')],
                    ARRAY[COALESCE(r.historical_name, \'\')],
                    ARRAY[COALESCE(r.is_city::text, \'\')],
                    ARRAY[COALESCE(p.identifier, \'\')],
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
                    array_append(rec.ids, r.identity),
                    array_append(rec.names, COALESCE(r.name, \'\')),
                    array_append(rec.historical_names, COALESCE(r.historical_name, \'\')),
                    array_append(rec.is_cities, COALESCE(r.is_city::text, \'\')),
                    array_append(rec.pleiades_ids, COALESCE(p.identifier, \'\')),
                    rec.depth + 1

                FROM rec
                INNER JOIN data.region r
                ON rec.id = r.parent_idregion
                left join (
                    select global_id.idsubject, global_id.identifier
                    from data.global_id
                    inner join data.institution
                        on global_id.idauthority = institution.identity
                        and institution.name = \'Pleiades\'
                ) as p on r.identity = p.idsubject

            )
            SELECT
                array_to_json(ids) as ids,
                array_to_json(names) as names,
                array_to_json(historical_names) as historical_names,
                array_to_json(is_cities) as is_cities,
                array_to_json(pleiades_ids) as pleiades_ids
            FROM rec
            INNER JOIN (
                SELECT id, MAX(depth) AS maxdepth
                FROM rec
                GROUP BY id
            ) rm
            ON rec.id = rm.id AND rec.depth = rm.maxdepth
            WHERE rec.id in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  int|null    $parentId
     * @param  string      $name
     * @param  string|null $historicalName
     * @param  bool        $isCity
     * @return int
     */
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
        $id = $this->conn->executeQuery(
            'SELECT
                region.identity as region_id
            from data.region
            order by identity desc
            limit 1'
        )->fetch()['region_id'];
        return $id;
    }

    /**
     * @param  int      $id
     * @param  int|null $parentId
     * @return int
     */
    public function updateParent(int $id, int $parentId = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set parent_idregion = ?
            where region.identity = ?',
            [
                $parentId,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $name
     * @return int
     */
    public function updateName(int $id, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set name = ?
            where region.identity = ?',
            [
                $name,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $historicalName
     * @return int
     */
    public function updateHistoricalName(int $id, string $historicalName): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set historical_name = ?
            where region.identity = ?',
            [
                $historicalName,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $pleiades
     * @return int
     */
    public function upsertPleiades(int $id, int $pleiades): int
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
                $id,
                $pleiades,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $isCity
     * @return int
     */
    public function updateIsCity(int $id, bool $isCity): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set is_city = ?
            where region.identity = ?',
            [
                $isCity ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        // don't delete if this region is used in region (as parent)
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.region
            where region.parent_idregion = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This region has region dependencies.');
        }
        // don't delete if this region is used in located_at
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.located_at
            inner join data.location on located_at.idlocation = location.idlocation
            where location.idregion = ?',
            [$id]
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
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This region has factoid dependencies.');
        }
        // don't delete if this region is used in institution
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.institution
            where institution.idregion = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This region has institution dependencies.');
        }
        //TODO: person dependency
        // Set search_path for trigger delete_entity
        // Pleiades id is deleted by foreign key constraint
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.region
            where region.identity = ?',
            [$id]
        );
    }
}
