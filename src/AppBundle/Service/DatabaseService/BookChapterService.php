<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class BookChapterService extends DocumentService
{
    /**
     * Get all book chapter ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                bookchapter.identity as book_chapter_id
            from data.bookchapter'
        )->fetchAll();
    }

    /**
     * Get all ids of book chapters that are dependent on a specific book
     * @param  int   $bookId
     * @return array
     */
    public function getDepIdsByBookId(int $bookId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bookchapter.identity as book_chapter_id
            from data.bookchapter
            inner join data.document_contains on bookchapter.identity = document_contains.idcontent
            where document_contains.idcontainer = ?',
            [$bookId]
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
                bookchapter.identity as book_chapter_id,
                document_title.title as book_chapter_title,
                array_to_json(array_agg(bibrole.idperson order by bibrole.rank)) as person_ids,
                book.identity as book_id,
                document_contains.page_start as book_chapter_page_start,
                document_contains.page_end as book_chapter_page_end,
                case when document_contains.page_start is null
                    then document_contains.physical_location_removeme
                    else null
                end as book_chapter_raw_pages
            from data.bookchapter
            left join data.bibrole on bookchapter.identity = bibrole.iddocument
            left join data.role on bibrole.idrole = role.idrole  and role.system_name = \'author\'
            inner join data.document_title on bookchapter.identity = document_title.iddocument
            inner join data.document_contains on bookchapter.identity = document_contains.idcontent
            inner join data.book on document_contains.idcontainer = book.identity
            where bookchapter.identity in (?)
            group by
                bookchapter.identity, document_title.title,
                book.identity,
                document_contains.page_start, document_contains.page_end, document_contains.physical_location_removeme',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string $title
     * @param  int    $bookId
     * @return int
     */
    public function insert(string $title, int $bookId): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_book_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.bookchapter default values'
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    bookchapter.identity as book_chapter_id
                from data.bookchapter
                order by identity desc
                limit 1'
            )->fetch()['book_chapter_id'];
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
                    $bookId,
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
     * @param  int $bookId
     * @return int
     */
    public function updateBook(int $id, int $bookId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set idcontainer = ?
            where document_contains.idcontent = ?',
            [
                $bookId,
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
            // don't delete if this book chapter is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This book chapter has dependencies.');
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
