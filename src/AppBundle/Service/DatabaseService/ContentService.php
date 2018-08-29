<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class ContentService extends DatabaseService
{
    public function getContentIds(): array
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

    public function getContentsWithParentsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (idgenre, idparentgenre, genre, ids, names, depth) AS (
            	SELECT
            		g.idgenre,
            		g.idparentgenre,
            		g.genre,
                    g.idgenre::text as ids,
                    g.genre AS names,
            		1
            	FROM data.genre g


            	UNION ALL

            	SELECT
            		g.idgenre,
            		g.idparentgenre,
            		g.genre,
                    r.ids || \':\' || g.idgenre::text AS ids,
                    r.names || \':\' || g.genre AS names,
            		r.depth + 1

            	FROM rec AS r
            	INNER JOIN data.genre g
            	ON r.idgenre = g.idparentgenre
            )
            SELECT r.idgenre, ids, names
            FROM rec r
            INNER JOIN (
            	SELECT idgenre, MAX(depth) AS maxdepth
            	FROM rec
            	GROUP BY idgenre
            ) rj
            ON r.idgenre = rj.idgenre AND r.depth = rj.maxdepth
            WHERE r.idgenre in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getContentsByContentId(int $contentId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                genre.idgenre as content_id
            from data.genre
            where genre.idparentgenre = ?',
            [$contentId]
        )->fetchAll();
    }

    public function insert(
        int $parentId = null,
        string $name
    ): int {
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
        $contentId = $this->conn->executeQuery(
            'SELECT
                genre.idgenre as content_id
            from data.genre
            order by idgenre desc
            limit 1'
        )->fetch()['content_id'];
        return $contentId;
    }

    public function updateParent(int $contentId, int $parentId = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.genre
            set idparentgenre = ?
            where genre.idgenre = ?',
            [
                $parentId,
                $contentId,
            ]
        );
    }

    public function updateName(int $contentId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.genre
            set genre = ?
            where genre.idgenre = ?',
            [
                $name,
                $contentId,
            ]
        );
    }

    public function delete(int $contentId): int
    {
        // don't delete if this content is used in document_genre
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.document_genre
            where document_genre.idgenre = ?',
            [$contentId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This content has dependencies.');
        }
        // don't delete if this content is used in content (as parent)
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.genre
            where genre.idparentgenre = ?',
            [$contentId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This content has dependencies.');
        }
        // Set search_path for trigger delete_entity
        // Pleiades id is deleted by foreign key constraint
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.genre
            where genre.idgenre = ?',
            [$contentId]
        );
    }
}
