<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class ArticleService extends DocumentService
{
    /**
     * Get all article ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                article.identity as article_id
            from data.article'
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.article on entity.identity = article.identity'
        )->fetch();
    }

    /**
     * Get all ids of articles that are dependent on a specific journal issue
     * @param  int   $journalIssueId
     * @return array
     */
    public function getDepIdsByJournalIssueId(int $journalIssueId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                article.identity as article_id
            from data.article
            inner join data.document_contains on article.identity = document_contains.idcontent
            where document_contains.idcontainer = ?',
            [$journalIssueId]
        )->fetchAll();
    }

    /**
     * Get all ids of articles that are dependent on a specific person
     * @param  int   $personId
     * @return array
     */
    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                article.identity as article_id
            from data.article
            inner join data.bibrole on article.identity = bibrole.iddocument
            where bibrole.idperson = ?',
            [$personId]
        )->fetchAll();
    }

    /**
     * Get all ids of articles that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getDepIdsByReferenceIds(array $referenceIds): array
    {
        return $this->conn->executeQuery(
            'SELECT
                article.identity as article_id
            from data.article
            inner join data.reference on article.identity = reference.idsource
            where reference.idreference in (?)',
            [$referenceIds],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByRoleId(int $roleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                article.identity as article_id
            from data.article
            inner join data.bibrole on article.identity = bibrole.iddocument
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                article.identity as article_id
            from data.article
            inner join data.entity_management on article.identity = entity_management.identity
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
                article.identity as article_id,
                document_title.title as article_title,
                array_to_json(array_agg(bibrole.idperson order by bibrole.rank)) as person_ids,
                journal_issue.identity as journal_issue_id,
                document_contains.page_start as article_page_start,
                document_contains.page_end as article_page_end,
                case when document_contains.page_start is null
                    then document_contains.physical_location_removeme
                    else null
                end as article_raw_pages
            from data.article
            left join data.bibrole on article.identity = bibrole.iddocument
            left join data.role on bibrole.idrole = role.idrole  and role.system_name = \'author\'
            inner join data.document_title on article.identity = document_title.iddocument
            inner join data.document_contains on article.identity = document_contains.idcontent
            inner join data.journal_issue on document_contains.idcontainer = journal_issue.identity
            where article.identity in (?)
            group by
                article.identity, document_title.title,
                journal_issue.identity,
                document_contains.page_start, document_contains.page_end, document_contains.physical_location_removeme',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string $title
     * @param  int    $journalIssueId
     * @return int    id of the new article
     */
    public function insert(string $title, int $journalIssueId): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_book_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.article default values'
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    article.identity as article_id
                from data.article
                order by identity desc
                limit 1'
            )->fetch()['article_id'];
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
                    $journalIssueId,
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
     * @param  int $journalIssueId
     * @return int
     */
    public function updateJournalIssue(int $id, int $journalIssueId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set idcontainer = ?
            where document_contains.idcontent = ?',
            [
                $journalIssueId,
                $id,
            ]
        );
    }

    public function updateStartPage(int $id, int $startPage): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set page_start = ?
            where document_contains.idcontent = ?',
            [
                $startPage,
                $id,
            ]
        );
    }

    public function updateEndPage(int $id, int $endPage): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set page_end = ?
            where document_contains.idcontent = ?',
            [
                $endPage,
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
            // don't delete if this article is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This article has dependencies.');
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
