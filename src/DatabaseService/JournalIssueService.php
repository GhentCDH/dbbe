<?php

namespace App\DatabaseService;

use Exception;

use App\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class JournalIssueService extends DatabaseService
{
    /**
     * Get all journal issue ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                journal_issue.identity as journal_issue_id
            from data.journal_issue'
        )->fetchAll();
    }

    /**
     * Get all ids of journal issues that are dependent on a specific journal
     * @param  int   $journalId
     * @return array
     */
    public function getDepIdsByJournalId(int $journalId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                identity as journal_issue_id
            from data.journal_issue
            where idjournal = ?',
            [$journalId]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getJournalIssuesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                identity as journal_issue_id,
                idjournal as journal_id,
                year,
                forthcoming,
                volume,
                number
            from data.journal_issue
            where identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string|null $year
     * @param  bool        $forthcoming
     * @param  string|null $volume
     * @param  string|null $number
     * @return int
     */
    public function insert(int $journalId, string $year = null, bool $forthcoming, string $volume = null, string $number = null): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_journal_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.journal_issue (idjournal, year, forthcoming, volume, number)
                values (?, ?, ?, ?, ?)',
                [
                    $journalId,
                    $year,
                    $forthcoming ? 't' : 'f',
                    $volume,
                    $number,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    journal_issue.identity as journal_issue_id
                from data.journal_issue
                order by identity desc
                limit 1'
            )->fetch()['journal_issue_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * @param  int $id
     * @param  int $journalId
     * @return int
     */
    public function updateJournal(int $id, int $journalId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.journal_issue
            set idjournal = ?
            where journal_issue.identity = ?',
            [
                $journalId,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $year
     * @return int
     */
    public function updateYear(int $id, string $year = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.journal_issue
            set year = ?
            where journal_issue.identity = ?',
            [
                $year,
                $id,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $forthcoming
     * @return int
     */
    public function updateForthcoming(int $id, bool $forthcoming): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.journal_issue
            set forthcoming = ?
            where journal_issue.identity = ?',
            [
                $forthcoming ? 't' : 'f',
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
            'UPDATE data.journal_issue
            set volume = ?
            where journal_issue.identity = ?',
            [
                $volume,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $number
     * @return int
     */
    public function updateNumber(int $id, string $number = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.journal_issue
            set number = ?
            where journal_issue.identity = ?',
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
        // don't delete if this journal issue is used in document_contains
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.document_contains
            where document_contains.idcontainer = ?',
            [$id]
        )->fetchOne(0);
        if ($count > 0) {
            throw new DependencyException('This journal issue has dependencies.');
        }
        // Set search_path for triggers
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.journal_issue
            where journal_issue.identity = ?',
            [
                $id,
            ]
        );
    }
}
