<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class RegionService extends DatabaseService
{
    public function getRegionsWithParentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (identity, parent_idregion, name, historical_name, ids, names, historical_names, depth) AS (
                SELECT
                    r.identity,
                    r.parent_idregion,
                    r.name,
                    r.historical_name,
                    r.identity::text AS ids,
                    r.name AS names,
                    r.historical_name AS historical_names,
                    1
                FROM data.region r

                UNION ALL

                SELECT
                    r.identity,
                    r.parent_idregion,
                    r.name,
                    r.historical_name,
                    rec.ids || \':\' || r.identity::text AS ids,
                    rec.names || \':\' || COALESCE(r.name, \'\') AS names,
                    rec.historical_names || \':\' || COALESCE(r.historical_name, \'\') AS historical_names,
                    rec.depth + 1

                FROM rec
                INNER JOIN data.region r
                ON rec.identity = r.parent_idregion
            )
            SELECT rec.identity, ids, names, historical_names
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

    public function getRegionsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                region.identity as region_id,
                region.name,
                region.historical_name
            from data.region
            where region.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function updateName(int $regionId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.region
            set name = ?
            where region.identity = ?',
            [$name, $regionId]
        );
    }
}
