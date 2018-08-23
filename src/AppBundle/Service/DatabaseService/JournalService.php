<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class JournalService extends DatabaseService
{
    /**
     * Get all journal ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                journal.identity as journal_id
            from data.journal'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getJournalsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                journal.identity as journal_id,
                document_title.title,
                journal.year,
                journal.volume,
                journal.number
            from data.journal
            inner join data.document_title on journal.identity = document_title.iddocument
            where journal.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string   $title
     * @param  int      $year
     * @param  int|null $volume
     * @param  int|null $number
     * @return int
     */
    public function insert(string $title, int $year, int $volume = null, int $number = null): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_journal_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.journal (year, volume, number)
                values (?, ?, ?)',
                [
                    $year,
                    $volume,
                    $number,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    journal.identity as journal_id
                from data.journal
                order by identity desc
                limit 1'
            )->fetch()['journal_id'];
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
     * @param  string $title
     * @return int
     */
    public function updateTitle(int $id, string $title): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_title
            set title = ?
            where document_title.iddocument = ?',
            [
                $title,
                $id,
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
            'UPDATE data.journal
            set year = ?
            where journal.identity = ?',
            [
                $year,
                $id,
            ]
        );
    }

    /**
     * @param  int      $id
     * @param  int|null $volume
     * @return int
     */
    public function updateVolume(int $id, int $volume = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.journal
            set volume = ?
            where journal.identity = ?',
            [
                $volume,
                $id,
            ]
        );
    }

    /**
     * @param  int      $id
     * @param  int|null $number
     * @return int
     */
    public function updateNumber(int $id, int $number = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.journal
            set number = ?
            where journal.identity = ?',
            [
                $number,
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
        // don't delete if this journal is used in document_contains
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.document_contains
            where document_contains.idcontainer = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This journal has dependencies.');
        }
        return $this->conn->executeUpdate(
            'DELETE from data.journal
            where journal.identity = ?',
            [
                $id,
            ]
        );
    }
}
