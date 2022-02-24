<?php

namespace App\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use App\Exceptions\DependencyException;

class KeywordService extends DatabaseService
{
    /**
     * Get all subject keyword ids
     * @return array
     */
    public function getSubjectIds(): array
    {
        return $this->conn->query(
            'SELECT
                keyword.identity as keyword_id
            from data.keyword
            where keyword.is_subject = TRUE'
        )->fetchAll();
    }

    /**
     * Get all type keyword ids
     * @return array
     */
    public function getTypeIds(): array
    {
        return $this->conn->query(
            'SELECT
                keyword.identity as keyword_id
            from data.keyword
            where keyword.is_subject = FALSE'
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
     * @param  string $name
     * @param  bool   $isSubject
     * @return int
     */
    public function insert(string $name, bool $isSubject): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_keyword_has_identity
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.keyword (keyword, is_subject)
                values (?, ?)',
                [
                    $name,
                    $isSubject ? 'TRUE': 'FALSE',
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

    public function migrateSubjectFactoidToPerson(int $keywordId, int $personId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set subject_identity = ?
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = \'subject of\'',
            [
                $personId,
                $keywordId,
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
        )->fetchOne(0);
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
