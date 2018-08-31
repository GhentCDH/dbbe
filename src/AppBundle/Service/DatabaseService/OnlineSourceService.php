<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class OnlineSourceService extends EntityService
{
    /**
     * Get all online source ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                online_source.identity as online_source_id
            from data.online_source'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getMiniInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                online_source.identity as online_source_id,
                online_source.url,
                online_source.last_accessed,
                institution.name as institution_name
            from data.online_source
            inner join data.institution on online_source.identity = institution.identity
            where online_source.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string $url
     * @param  string $name
     * @param  string $lastAccessed
     * @return int
     */
    public function insert(string $url, string $name, string $lastAccessed): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_book_has_institution
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.online_source (url, last_accessed) values (?, ?)',
                [
                    $url,
                    $lastAccessed,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    online_source.identity as online_source_id
                from data.online_source
                order by identity desc
                limit 1'
            )->fetch()['online_source_id'];
            $this->conn->executeQuery(
                'UPDATE data.institution
                set name = ?
                where institution.identity = ?',
                [
                    $name,
                    $id,
                ]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * @param  int    $id
     * @param  string $url
     * @return int
     */
    public function updateUrl(int $id, string $url): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.online_source
            set url = ?
            where online_source.identity = ?',
            [
                $url,
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
            'UPDATE data.institution
            set name = ?
            where institution.identity = ?',
            [
                $name,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $lastAccessed
     * @return int
     */
    public function updateLastAccessed(int $id, string $lastAccessed): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.online_source
            set last_accessed = ?
            where online_source.identity = ?',
            [
                $lastAccessed,
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
        $this->beginTransaction();
        try {
            // don't delete if this online source is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This online source has dependencies.');
            }
            // Set search_path for triggers
            $this->conn->exec('SET SEARCH_PATH TO data');
            $delete = $this->conn->executeUpdate(
                'DELETE from data.entity
                where entity.identity = ?',
                [$id]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $delete;
    }
}
