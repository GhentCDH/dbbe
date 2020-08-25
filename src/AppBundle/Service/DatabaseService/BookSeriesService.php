<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class BookSeriesService extends DocumentService
{
    /**
     * Get all book series ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                book_series.identity as book_series_id
            from data.book_series'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getBookSeriessByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book_series.identity as book_series_id,
                document_title.title
            from data.book_series
            inner join data.document_title on book_series.identity = document_title.iddocument
            where book_series.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getBooks(int $id): array
    {
        return $this->conn->executeQuery(
            'select 
                book.identity as book_id
            from data.book_series
            inner join data.book on book_series.identity = book.idseries
            where book_series.identity = ?',
            [$id]
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
            // Set search_path for trigger ensure_book_series_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.book_series DEFAULT VALUES'
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    book_series.identity as book_series_id
                from data.book_series
                order by identity desc
                limit 1'
            )->fetch()['book_series_id'];
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
        // don't delete if this book_series is used in a book
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.book
            where book.idseries = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This book series has dependencies.');
        }
        // Set search_path for triggers
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.book_series
            where book_series.identity = ?',
            [
                $id,
            ]
        );
    }
}
