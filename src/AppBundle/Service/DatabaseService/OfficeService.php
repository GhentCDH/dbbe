<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class OfficeService extends DatabaseService
{
    /**
     * Get all office ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
            occupation.idoccupation as office_id,
            occupation.occupation as name
            from data.occupation'
        )->fetchAll();
    }

    /**
     * Get the ids of all childs of a specific office
     * @param  int $id
     * @return array
     */

    public function getChildIds(int $id): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (id, idparent) AS (
                SELECT
                    o.idoccupation,
                    o.idparentoccupation
                FROM data.occupation o

                UNION ALL

                SELECT
                    rec.id,
                    o.idparentoccupation
                FROM rec
                INNER JOIN data.occupation o
                ON o.idoccupation = rec.idparent
            )
            SELECT id as child_id
            FROM rec
            WHERE rec.idparent = ?',
            [$id]
        )->fetchAll();
    }

    /**
     * Get all ids of offices that are dependent on a specific office
     * @param  int   $officeId
     * @return array
     */
    public function getDepIdsByOfficeId(int $officeId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as office_id
            from data.occupation
            where occupation.idparentoccupation = ?',
            [$officeId]
        )->fetchAll();
    }

    /**
     * Get all offices that are dependent on a specific region
     * @param  int   $regionId
     * @return array
     */
    public function getDepIdsByRegionId(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as office_id
            from data.occupation
            where occupation.idregion = ?',
            [$regionId]
        )->fetchAll();
    }

    /**
     * Get all offices that are dependent on a specific region or one of its children
     * @param  int   $regionId
     * @return array
     */
    public function getDepIdsByRegionIdWithChildren(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as office_id
            from data.occupation
            where occupation.idregion in (
                WITH RECURSIVE rec (id, idparent) AS (
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
                SELECT id
                FROM rec
                WHERE rec.idparent = ? or rec.id = ?
            )',
            [
                $regionId,
                $regionId,
            ]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getOfficesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occupation.idoccupation as office_id,
                occupation.occupation as name,
                occupation.idregion as region_id
            from data.occupation
            where occupation.idoccupation in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getOfficesWithParentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (id, ids, names, regions, depth) AS (
                SELECT
                    o.idoccupation,
                    ARRAY[o.idoccupation],
                    ARRAY[o.occupation],
                    ARRAY[o.idregion],
                    1
                FROM data.occupation o


                UNION ALL

                SELECT
                    o.idoccupation,
                    array_append(rec.ids, o.idoccupation),
                    array_append(rec.names, o.occupation),
                    array_append(rec.regions, o.idregion),
                    rec.depth + 1

                FROM rec
                INNER JOIN data.occupation o
                ON rec.id = o.idparentoccupation
            )
            SELECT
                array_to_json(ids) as ids,
                array_to_json(names) as names,
                array_to_json(regions) as regions
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
     * [insert description]
     * @param  int      $parentId
     * @param  string   $name     '' if unused
     * @param  int|null $regionId
     * @return int
     */
    public function insert(int $parentId = null, string $name, int $regionId = null): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.occupation (idparentoccupation, occupation, idregion)
                values (?, ?, ?)',
                [
                    $parentId,
                    $name,
                    $regionId,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    occupation.idoccupation as office_id
                from data.occupation
                order by idoccupation desc
                limit 1'
            )->fetch()['office_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
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
            'UPDATE data.occupation
            set idparentoccupation = ?, modified = now()
            where occupation.idoccupation = ?',
            [
                $parentId,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $name '' if unused
     * @return int
     */
    public function updateName(int $id, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.occupation
            set occupation = ?, modified = now()
            where occupation.idoccupation = ?',
            [
                $name,
                $id,
            ]
        );
    }

    /**
     * @param  int      $id
     * @param  int|null $regionId
     * @return int
     */
    public function updateRegion(int $id, int $regionId = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.occupation
            set idregion = ?, modified = now()
            where occupation.idoccupation = ?',
            [
                $regionId,
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
        // don't delete if this office is used in office (as parent)
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.occupation
            where occupation.idparentoccupation = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This office has office dependencies.');
        }
        // don't delete if this office is used in person_occupation
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.person_occupation
            where person_occupation.idoccupation = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This office has person dependencies.');
        }

        return $this->conn->executeUpdate(
            'DELETE from data.occupation
            where occupation.idoccupation = ?',
            [
                $id,
            ]
        );
    }
}
