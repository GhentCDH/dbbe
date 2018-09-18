<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class KeywordService extends DatabaseService
{
    /**
     * Get all keyword ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                keyword.identity as keyword_id
            from data.keyword'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getKeywordsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                keyword.identity as keyword_id,
                keyword.keyword as name
            from data.keyword
            where keyword.identity in (?)',
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
            // Set search_path for trigger ensure_keyword_has_identity
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.keyword (keyword)
                values (?)',
                [
                    $name
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    keyword.identity as keyword_id
                from data.keyword
                order by identity desc
                limit 1'
            )->fetch()['keyword_id'];
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
            'UPDATE data.keyword
            set keyword = ?
            where keyword.identity = ?',
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
        // don't delete if this keyword is used in factoid
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.factoid
            where factoid.subject_identity = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This keyword has dependencies.');
        }

        return $this->conn->executeUpdate(
            'DELETE from data.keyword
            where keyword.identity = ?',
            [$id]
        );
    }
}
