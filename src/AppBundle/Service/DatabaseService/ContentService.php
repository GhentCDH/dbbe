<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class ContentService extends DatabaseService
{
    /**
     * Get all office ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                genre.idgenre as content_id
            from data.genre
            where genre.is_content = TRUE'
        )->fetchAll();
    }

    /**
     * Get the ids of all childs of a specific content
     * @param  int $id
     * @return array
     */

    public function getChildIds(int $id): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (id, idparent) AS (
                SELECT
                    g.idgenre,
                    g.idparentgenre
                FROM data.genre g

                UNION ALL

                SELECT
                    rec.id,
                    g.idparentgenre
                FROM rec
                INNER JOIN data.genre g
                ON g.idgenre = rec.idparent
            )
            SELECT id as child_id
            FROM rec
            WHERE rec.idparent = ?',
            [$id]
        )->fetchAll();
    }

    /**
     * Get all ids of contents that are dependent on a specific content
     * @param  int   $contentId
     * @return array
     */
    public function getDepIdsByContentId(int $contentId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                genre.idgenre as content_id
            from data.genre
            where genre.idparentgenre = ?',
            [$contentId]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getContentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                genre.identity as content_id,
                genre.name
            from data.genre
            where genre.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getContentsWithParentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (id, ids, names, depth) AS (
            	SELECT
            		g.idgenre,
                    ARRAY[g.idgenre],
                    ARRAY[g.genre],
            		1
            	FROM data.genre g


            	UNION ALL

            	SELECT
            		g.idgenre,
                    array_append(rec.ids, g.idgenre),
                    array_append(rec.names, g.genre),
            		rec.depth + 1

            	FROM rec
            	INNER JOIN data.genre g
            	ON rec.id = g.idparentgenre
            )
            SELECT
                array_to_json(ids) as ids,
                array_to_json(names) as names
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
     * @param  int|null $parentId
     * @param  string   $name
     * @return int
     */
    public function insert(int $parentId = null, string $name): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_entity_presence
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.genre (idparentgenre, genre, is_content)
                values (?, ?, TRUE)',
                [
                    $parentId,
                    $name
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    genre.idgenre as content_id
                from data.genre
                order by idgenre desc
                limit 1'
            )->fetch()['content_id'];
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
            'UPDATE data.genre
            set idparentgenre = ?
            where genre.idgenre = ?',
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
            'UPDATE data.genre
            set genre = ?
            where genre.idgenre = ?',
            [
                $name,
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
        // don't delete if this content is used in content (as parent)
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.genre
            where genre.idparentgenre = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This content has dependencies.');
        }
        // don't delete if this content is used in document_genre
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.document_genre
            where document_genre.idgenre = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This content has dependencies.');
        }

        return $this->conn->executeUpdate(
            'DELETE from data.genre
            where genre.idgenre = ?',
            [$id]
        );
    }
}
