<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class JournalService extends DocumentService
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
                document_title.title
            from data.journal
            inner join data.document_title on journal.identity = document_title.iddocument
            where journal.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getIssuesArticles(int $id): array
    {
        return $this->conn->executeQuery(
            'select 
                journal_issue.identity as journal_issue_id,
                article.identity as article_id
            from data.journal
            inner join data.journal_issue on journal.identity = journal_issue.idjournal
            inner join data.document_contains on journal_issue.identity = document_contains.idcontainer
            inner join data.article on document_contains.idcontent = article.identity
            where journal.identity = ?
            order by journal_issue.year, journal_issue.volume, journal_issue.number',
            [$id]
        )->fetchAll();
    }

    /**
     * @param  string   $title
     * @param  int      $year
     * @param  int|null $volume
     * @param  int|null $number
     * @return int
     */
    public function insert(string $title): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_journal_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.journal DEFAULT VALUES'
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
     * @return int
     */
    public function delete(int $id): int
    {
        // don't delete if this journal is used in a journal issue
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.journal_issue
            where journal_issue.idjournal = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This journal has dependencies.');
        }
        // Set search_path for triggers
        $this->conn->exec('SET SEARCH_PATH TO data');
        return $this->conn->executeUpdate(
            'DELETE from data.journal
            where journal.identity = ?',
            [
                $id,
            ]
        );
    }
}
