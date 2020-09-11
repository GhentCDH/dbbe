<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class BlogService extends EntityService
{
    /**
     * Get all blog ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                blog.identity as blog_id
            from data.blog'
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.blog on entity.identity = blog.identity'
        )->fetch();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getMiniInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                blog.identity as blog_id,
                blog.url,
                blog.last_accessed,
                document_title.title
            from data.blog
            inner join data.document_title on blog.identity = document_title.iddocument
            where blog.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * Get all ids of blogs that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getDepIdsByReferenceIds(array $referenceIds): array
    {
        return $this->conn->executeQuery(
            'SELECT
                blog.identity as blog_id
            from data.blog
            inner join data.reference on blog.identity = reference.idsource
            where reference.idreference in (?)',
            [$referenceIds],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                blog.identity as blog_id
            from data.blog
            inner join data.entity_management on blog.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
        )->fetchAll();
    }

    /**
     * @param  string $url
     * @param  string $title
     * @param  string|null $lastAccessed
     * @return int
     */
    public function insert(string $url, string $title, string $lastAccessed = null): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_blog_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.blog (url, last_accessed) values (?, ?)',
                [
                    $url,
                    $lastAccessed,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    blog.identity as blog_id
                from data.blog
                order by identity desc
                limit 1'
            )->fetch()['blog_id'];
            $this->conn->executeQuery(
                'INSERT INTO data.document_title (iddocument, idlanguage, title)
                values (?, (select idlanguage from data.language where name = \'Unknown\'), ?)',
                [
                    $id,
                    $title,
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
            'UPDATE data.blog
            set url = ?
            where blog.identity = ?',
            [
                $url,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string|null $lastAccessed
     * @return int
     */
    public function updateLastAccessed(int $id, string $lastAccessed = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.blog
            set last_accessed = ?
            where blog.identity = ?',
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
            // don't delete if this blog is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This blog has reference dependencies.');
            }
            // don't delete if this blog is used in global_id
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.global_id
                where global_id.idauthority = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This blog has global_id dependencies.');
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
