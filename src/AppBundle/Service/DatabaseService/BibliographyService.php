<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class BibliographyService extends DatabaseService
{
    public function getBibliographiesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference.idreference as reference_id,
                reference.idsource as source_id,
                reference.page_start,
                reference.page_end,
                reference.url as rel_url
            from data.reference
            where reference.idreference in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function addBibliography(
        int $targetId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $url = null
    ): int {
        return $this->conn->executeUpdate(
            'INSERT INTO data.reference (idtarget, idsource, page_start, page_end, url)
            values (?, ?, ?, ?, ?)',
            [
                $targetId,
                $sourceId,
                $startPage,
                $endPage,
                $url,
            ]
        );
    }

    public function updateBibliography(
        int $referenceId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $url = null
    ): int {
        return $this->conn->executeUpdate(
            'UPDATE data.reference
            set idsource = ?, page_start = ?, page_end = ?, url = ?
            where idreference = ?',
            [
                $sourceId,
                $startPage,
                $endPage,
                $url,
                $referenceId,
            ]
        );
    }

    public function delBibliographies(array $ids): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.reference
            where reference.idreference in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function getBooksByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                book.identity as book_id,
                array_to_json(array_agg(bibrole.idperson order by bibrole.rank)) as person_ids,
                document_title.title,
                book.year,
                book.city,
                book.editor
            from data.book
            left join data.bibrole on book.identity = bibrole.iddocument and bibrole.type = \'author\'
            inner join data.document_title on book.identity = document_title.iddocument
            where book.identity in (?)
            group by book.identity, document_title.title',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getBookIds(): array
    {
        return $this->conn->query(
            'SELECT
                book.identity as book_id
            from data.book'
        )->fetchAll();
    }

    public function getArticlesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                article.identity as article_id,
                document_title.title as article_title,
                array_to_json(array_agg(bibrole.idperson order by bibrole.rank)) as person_ids,
                journal.identity as journal_id,
                document_contains.page_start as article_page_start,
                document_contains.page_end as article_page_end
            from data.article
            left join data.bibrole on article.identity = bibrole.iddocument and bibrole.type = \'author\'
            inner join data.document_title on article.identity = document_title.iddocument
            inner join data.document_contains on article.identity = document_contains.idcontent
            inner join data.journal on journal.identity = document_contains.idcontainer
            where article.identity in (?)
            group by
                article.identity, document_title.title,
                journal.identity,
                document_contains.page_start, document_contains.page_end',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

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

    public function getArticleIds(): array
    {
        return $this->conn->query(
            'SELECT
                article.identity as article_id
            from data.article'
        )->fetchAll();
    }

    public function getBookChaptersByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bookchapter.identity as book_chapter_id,
                document_title.title as book_chapter_title,
                array_to_json(array_agg(bibrole.idperson order by bibrole.rank)) as person_ids,
                book.identity as book_id,
                document_contains.page_start as book_chapter_page_start,
                document_contains.page_end as book_chapter_page_end
            from data.bookchapter
            left join data.bibrole on bookchapter.identity = bibrole.iddocument and bibrole.type = \'author\'
            inner join data.document_title on bookchapter.identity = document_title.iddocument
            inner join data.document_contains on bookchapter.identity = document_contains.idcontent
            inner join data.book on book.identity = document_contains.idcontainer
            where bookchapter.identity in (?)
            group by
                bookchapter.identity, document_title.title,
                book.identity,
                document_contains.page_start, document_contains.page_end',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getBookChapterIds(): array
    {
        return $this->conn->query(
            'SELECT
                bookchapter.identity as book_chapter_id
            from data.bookchapter'
        )->fetchAll();
    }

    public function getOnlineSourcesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                online_source.identity as online_source_id,
                online_source.url,
                online_source.last_accessed,
                institution.name as institution_name
            from data.online_source
            inner join data.institution on online_source.identity = institution.identity
            where online_source.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOnlineSourceIds(): array
    {
        return $this->conn->query(
            'SELECT
                online_source.identity as online_source_id
            from data.online_source'
        )->fetchAll();
    }
}
