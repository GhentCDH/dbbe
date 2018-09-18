<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class GenreService extends DatabaseService
{
    /**
     * Get all genre ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                genre.idgenre as genre_id
            from data.genre
            where genre.is_content = FALSE'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getGenresByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                genre.idgenre as genre_id,
                genre.genre as name
            from data.genre
            where genre.idgenre in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string   $name
     * @return int
     */
    public function insert(string $name): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.genre (idparentgenre, genre, is_content)
                values (
                    (select genre.idgenre from data.genre where genre.genre = \'DBBE system\'),
                    ?,
                    FALSE
                )',
                [
                    $name,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    genre.idgenre as genre_id
                from data.genre
                order by idgenre desc
                limit 1'
            )->fetch()['genre_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
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
        // don't delete if this genre is used in document_genre
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.document_genre
            where document_genre.idgenre = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This genre has dependencies.');
        }

        return $this->conn->executeUpdate(
            'DELETE from data.genre
            where genre.idgenre = ?',
            [$id]
        );
    }
}
