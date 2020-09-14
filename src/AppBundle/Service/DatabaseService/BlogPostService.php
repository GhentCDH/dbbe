<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class BlogPostService extends DocumentService
{
    /**
     * Get all blog post ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                blog_post.identity as blog_post_id
            from data.blog_post'
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.blog_post on entity.identity = blog_post.identity'
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
                blog_post.identity as blog_post_id,
                blog_post.url,
                blog_post.post_date,
                document_title.title,
                document_contains.idcontainer as blog_id
            from data.blog_post
            inner join data.document_title on blog_post.identity = document_title.iddocument
            inner join data.document_contains on blog_post.identity = document_contains.idcontent
            where blog_post.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * Get all ids of blog posts that are dependent on a specific blog
     * @param  int   $blogId
     * @return array
     */
    public function getDepIdsByBlogId(int $blogId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                blog_post.identity as blog_post_id
            from data.blog_post
            inner join data.document_contains on blog_post.identity = document_contains.idcontent
            where document_contains.idcontainer = ?',
            [$blogId]
        )->fetchAll();
    }

    /**
     * Get all ids of blog posts that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getDepIdsByReferenceIds(array $referenceIds): array
    {
        return $this->conn->executeQuery(
            'SELECT
                blog_post.identity as blog_post_id
            from data.blog_post
            inner join data.reference on blog_post.identity = reference.idsource
            where reference.idreference in (?)',
            [$referenceIds],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                blog_post.identity as blog_post_id
            from data.blog_post
            inner join data.entity_management on blog_post.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
        )->fetchAll();
    }

    /**
     * @param int $blogId
     * @param string $url
     * @param string $title
     * @param string $postDate
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function insert(int $blogId, string $url, string $title, string $postDate): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_blog_post_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.blog_post (url, post_date) values (?, ?)',
                [
                    $url,
                    $postDate,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    blog_post.identity as blog_post_id
                from data.blog_post
                order by identity desc
                limit 1'
            )->fetch()['blog_post_id'];
            $this->conn->executeQuery(
                'INSERT INTO data.document_title (iddocument, idlanguage, title)
                values (?, (select idlanguage from data.language where name = \'Unknown\'), ?)',
                [
                    $id,
                    $title,
                ]
            );
            $this->conn->executeQuery(
                'INSERT INTO data.document_contains (idcontainer, idcontent)
                values (?, ?)',
                [
                    $blogId,
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
     * @param  int $id
     * @param  int $blogId
     * @return int
     */
    public function updateBlog(int $id, int $blogId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set idcontainer = ?
            where blog_post.identity = ?',
            [
                $blogId,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $url
     * @return int
     */
    public function updateUrl(int $id, string $url): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.blog_post
            set url = ?
            where blog_post.identity = ?',
            [
                $url,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $postDate
     * @return int
     */
    public function updatePostDate(int $id, string $postDate): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.blog_post
            set post_Date = ?
            where blog_post.identity = ?',
            [
                $postDate,
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
            // don't delete if this blog post is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This blog post has reference dependencies.');
            }
            // don't delete if this blog post is used in global_id
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.global_id
                where global_id.idauthority = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This blog post has global_id dependencies.');
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
