<?php

namespace App\DatabaseService;

use Exception;

use App\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class BookClusterService extends DocumentService
{
    /**
     * Get all book cluster ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                book_cluster.identity as book_cluster_id
            from data.book_cluster'
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.book_cluster on entity.identity = book_cluster.identity'
        )->fetch();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getBookClustersByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book_cluster.identity as book_cluster_id,
                document_title.title
            from data.book_cluster
            inner join data.document_title on book_cluster.identity = document_title.iddocument
            where book_cluster.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAll(): array
    {
        return $this->conn->executeQuery(
            'SELECT
            book_cluster.identity as book_cluster_id,
            document_title.title,
            array_to_json(array_agg(entity_url.idurl)) as url_ids,
            array_to_json(array_agg(entity_url.url)) as url_urls,
            array_to_json(array_agg(entity_url.title)) as url_titles
            from data.book_cluster
            inner join data.document_title on book_cluster.identity = document_title.iddocument
            left join data.entity_url on book_cluster.identity = entity_url.identity
            group by book_cluster.identity, document_title.title'
        )->fetchAll();
    }

    public function getBooks(array $ids): array
    {
        return $this->conn->executeQuery(
            'select
                book_cluster.identity as book_cluster_id,
                book.identity as book_id
            from data.book_cluster
            inner join data.book on book_cluster.identity = book.idcluster
            where book_cluster.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string   $title
     * @return int
     */
    public function insert(string $title): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_book_cluster_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.book_cluster DEFAULT VALUES'
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    book_cluster.identity as book_cluster_id
                from data.book_cluster
                order by identity desc
                limit 1'
            )->fetch()['book_cluster_id'];
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
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        // don't delete if this book_cluster is used in a book
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.book
            where book.idcluster = ?',
            [$id]
        )->fetchOne(0);
        if ($count > 0) {
            throw new DependencyException('This book cluster has dependencies.');
        }
        // Set search_path for triggers
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.book_cluster
            where book_cluster.identity = ?',
            [
                $id,
            ]
        );
    }
}
