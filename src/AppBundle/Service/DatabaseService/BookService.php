<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class BookService extends DocumentService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                book.identity as book_id
            from data.book'
        )->fetchAll();
    }

    public function getBasicInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id,
                document_title.title,
                book.year,
                book.city,
                book.editor
            from data.book
            inner join data.document_title on book.identity = document_title.iddocument
            where book.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getFullInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id,
                book.publisher,
                book.series,
                book.volume,
                book.total_volumes
            from data.book
            where book.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function insert(string $title, int $year, string $city): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_book_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.book (year, city)
                values (?, ?)',
                [
                    $year,
                    $city,
                ]
            );
            $bookId = $this->conn->executeQuery(
                'SELECT
                    book.identity as book_id
                from data.book
                order by identity desc
                limit 1'
            )->fetch()['book_id'];
            $this->conn->executeQuery(
                'INSERT INTO data.document_title (iddocument, idlanguage, title)
                values (?, (select idlanguage from data.language where name = \'Unknown\'), ?)',
                [
                    $bookId,
                    $title,
                ]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $bookId;
    }

    public function updateTitle(int $bookId, string $title): int
    {
        // TODO: test if upsert is needed for new books
        return $this->conn->executeUpdate(
            'UPDATE data.document_title
            set title = ?
            where document_title.iddocument = ?',
            [
                $title,
                $bookId,
            ]
        );
    }

    public function updateYear(int $bookId, int $year): int
    {
        // TODO: test if upsert is needed for new books
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set year = ?
            where book.identity = ?',
            [
                $year,
                $bookId,
            ]
        );
    }

    public function updateCity(int $bookId, string $city): int
    {
        // TODO: test if upsert is needed for new books
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set city = ?
            where book.identity = ?',
            [
                $city,
                $bookId,
            ]
        );
    }

    public function updateEditor(int $bookId, string $editor): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set editor = ?
            where book.identity = ?',
            [
                $editor,
                $bookId,
            ]
        );
    }

    public function updatePublisher(int $bookId, string $publisher): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set publisher = ?
            where book.identity = ?',
            [
                $publisher,
                $bookId,
            ]
        );
    }

    public function updateSeries(int $bookId, string $series): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set series = ?
            where book.identity = ?',
            [
                $series,
                $bookId,
            ]
        );
    }

    public function updateVolume(int $bookId, int $volume): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set volume = ?
            where book.identity = ?',
            [
                $volume,
                $bookId,
            ]
        );
    }

    public function updateTotalVolumes(int $bookId, int $totalVolumes): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set total_volumes = ?
            where book.identity = ?',
            [
                $totalVolumes,
                $bookId,
            ]
        );
    }
}
