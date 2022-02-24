<?php

namespace App\DatabaseService;

use Exception;

use App\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.book on entity.identity = book.identity'
        )->fetch();
    }

    /**
     * Get all ids of books that are dependent on a book cluster
     * @param  int   $bookClusterId
     * @return array
     */
    public function getDepIdsByBookClusterId(int $bookClusterId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id
            from data.book
            where book.idcluster = ?',
            [$bookClusterId]
        )->fetchAll();
    }

    /**
     * Get all ids of books that are dependent on a book series
     * @param  int   $bookSeriesId
     * @return array
     */
    public function getDepIdsByBookSeriesId(int $bookSeriesId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id
            from data.book
            where book.idseries = ?',
            [$bookSeriesId]
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
     * Get all ids of books that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getDepIdsByReferenceIds(array $referenceIds): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id
            from data.book
            inner join data.reference on book.identity = reference.idsource
            where reference.idreference in (?)',
            [$referenceIds],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByRoleId(int $roleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id
            from data.book
            inner join data.bibrole on book.identity = bibrole.iddocument
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id
            from data.book
            inner join data.entity_management on book.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
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
                book.idcluster as book_cluster_id,
                document_title.title,
                book.year,
                book.city,
                book.editor,
                book.volume
            from data.book
            left join data.document_title on book.identity = document_title.iddocument
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
                book.idseries as book_series_id,
                book.series_volume,
                book.total_volumes
            from data.book
            where book.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getChapters(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_contains.idcontainer as book_id,
                document_contains.idcontent as book_chapter_id
                from data.document_contains
                where document_contains.idcontainer in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  int|null    $bookClusterId
     * @param  string|null $title
     * @param  int         $year
     * @param  string      $city
     * @return int
     */
    public function insert(int $bookClusterId = null, string $title = null, int $year, string $city): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_book_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.book (idcluster, year, city)
                values (?, ?, ?)',
                [
                    $bookClusterId,
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
            if ($title != null) {
                $this->conn->executeQuery(
                    'INSERT INTO data.document_title (iddocument, idlanguage, title)
                    values (?, (select idlanguage from data.language where name = \'Unknown\'), ?)',
                    [
                        $id,
                        $title,
                    ]
                );
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * @param  int      $id
     * @param  int|null $bookClusterId
     * @return int
     */
    public function updateBookCluster(int $id, int $bookClusterId = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set idcluster = ?
            where book.identity = ?',
            [
                $bookClusterId,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $title
     * @return int
     */
    public function updateTitle(int $id, string $title = null): int
    {
        if (empty($title)) {
            return $this->conn->executeUpdate(
                'DELETE FROM data.document_title
                where document_title.iddocument = ?',
                [
                    $id
                ]
            );
        }
        return $this->conn->executeUpdate(
            'INSERT INTO data.document_title (iddocument, idlanguage, title)
            values (
                ?,
                (select idlanguage from data.language where name = \'Unknown\'),
                ?
            )
            -- primary key constraint on iddocument, idlanguage
            on conflict (iddocument, idlanguage) do update
            set title = excluded.title',
            [
                $id,
                $title,
            ]
        );
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
     * @param  int      $id
     * @param  int|null $seriesId
     * @return int
     */
    public function updateSeries(int $id, int $seriesId = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set idseries = ?
            where book.identity = ?',
            [
                $seriesId,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $seriesVolume
     * @return int
     */
    public function updateSeriesVolume(int $id, string $seriesVolume = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.book
            set series_volume = ?
            where book.identity = ?',
            [
                $seriesVolume,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $volume
     * @return int
     */
    public function updateVolume(int $id, string $volume = null): int
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
    public function updateTotalVolumes(int $id, int $totalVolumes = null): int
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
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This book has reference dependencies.');
            }
            // don't delete if this book is used in document_contains
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.document_contains
                where document_contains.idcontainer = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This book has document_contains dependencies.');
            }
            // don't delete if this book is used in global_id
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.global_id
                where global_id.idauthority = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This book has global_id dependencies.');
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
