<?php

namespace AppBundle\Service\DatabaseService;

class RegionService extends DatabaseService
{
    public function getRegionsWithParentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (identity, parent_idregion, name, ids, names, depth) AS (
                SELECT
                    r.identity,
                    r.parent_idregion,
                    r.name,
                    r.identity::text as ids,
                    r.name AS names,
                    1
                FROM data.region r

                UNION ALL

                SELECT
                    r.identity,
                    r.parent_idregion,
                    r.name,
                    rec.ids || \':\' || r.identity::text AS ids,
                    rec.names || \':\' || r.name AS names,
                    rec.depth + 1

                FROM rec
                INNER JOIN data.region r
                ON rec.identity = r.parent_idregion
            )
            SELECT rec.identity, ids, names
            FROM rec
            INNER JOIN (
                SELECT identity, MAX(depth) AS maxdepth
                FROM rec
                GROUP BY identity
            ) rj
            ON rec.identity = rj.identity AND rec.depth = rj.maxdepth
            WHERE rec.identity in (?)',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
