<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class BookService extends DocumentService
{
    /**
     * Get all book ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                book.identity as book_id
            from data.book'
        )->fetchAll();
    }

    /**
     * Get all ids of books that are dependent on a specific person
     * @param  int   $personId
     * @return array
     */
    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id
            from data.book
            inner join data.bibrole on book.identity = bibrole.iddocument
            where bibrole.idperson = ?',
            [$personId]
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

    /**
     * @param  array $ids
     * @return array
     */
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

    /**
     * @param  string $title
     * @param  int    $year
     * @param  string $city
     * @return int
     */
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
            $id = $this->conn->executeQuery(
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
     * @param  int $year
     * @return int
     */
    public function updateYear(int $id, int $year): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set year = ?
            where book.identity = ?',
            [
                $year,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $city
     * @return int
     */
    public function updateCity(int $id, string $city): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set city = ?
            where book.identity = ?',
            [
                $city,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $editor
     * @return int
     */
    public function updateEditor(int $id, string $editor): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set editor = ?
            where book.identity = ?',
            [
                $editor,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $publisher
     * @return int
     */
    public function updatePublisher(int $id, string $publisher): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set publisher = ?
            where book.identity = ?',
            [
                $publisher,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $series
     * @return int
     */
    public function updateSeries(int $id, string $series): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set series = ?
            where book.identity = ?',
            [
                $series,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $volume
     * @return int
     */
    public function updateVolume(int $id, int $volume): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set volume = ?
            where book.identity = ?',
            [
                $volume,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $totalVolumes
     * @return int
     */
    public function updateTotalVolumes(int $id, int $totalVolumes): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set total_volumes = ?
            where book.identity = ?',
            [
                $totalVolumes,
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
            // don't delete if this book is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This book has dependencies.');
            }
            // don't delete if this book is used in document_contains
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.document_contains
                where document_contains.idcontainer = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This book has dependencies.');
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
