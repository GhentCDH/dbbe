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
                article_title.title as article_title,
                array_to_json(array_agg(bibrole.idperson order by bibrole.rank)) as person_ids,
                journal.identity as journal_id,
                journal_title.title as journal_title,
                journal.year as journal_year,
                journal.volume as journal_volume,
                journal.number as journal_number,
                document_contains.page_start as article_page_start,
                document_contains.page_end as article_page_end
            from data.article
            left join data.bibrole on article.identity = bibrole.iddocument and bibrole.type = \'author\'
            inner join data.document_title as article_title on article.identity = article_title.iddocument
            inner join data.document_contains on article.identity = document_contains.idcontent
            inner join data.journal on journal.identity = document_contains.idcontainer
            inner join data.document_title as journal_title on journal.identity = journal_title.iddocument
            where article.identity in (?)
            group by
                article.identity, article_title.title,
                journal.identity, journal_title.title,
                document_contains.page_start, document_contains.page_end',
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

    public function getBookChapterBibliographiesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference.idreference as reference_id,
                bookchapter.identity as book_chapter_id,
                bookchapter_title.title as book_chapter_title,
                bibrole.idperson as person_id,
                book.identity as book_id,
                book.year as book_year,
                book.editor as book_editor,
                book_title.title as book_title,
                book.city as book_city,
                document_contains.page_start as book_chapter_page_start,
                document_contains.page_end as book_chapter_page_end,
                reference.page_start,
                reference.page_end
            from data.reference
            inner join data.bookchapter on reference.idsource = bookchapter.identity
            left join data.bibrole on bookchapter.identity = bibrole.iddocument and bibrole.type = \'author\'
            inner join data.document_title as bookchapter_title on bookchapter.identity = bookchapter_title.iddocument
            inner join data.document_contains on bookchapter.identity = document_contains.idcontent
            inner join data.book on book.identity = document_contains.idcontainer
            inner join data.document_title as book_title on book.identity = book_title.iddocument
            where reference.idreference in (?)
            order by bookchapter.identity, bibrole.rank',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOnlineSourceBibliographiesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference.idreference as reference_id,
                reference.url as rel_url,
                online_source.identity as online_source_id,
                online_source.url as base_url,
                online_source.last_accessed,
                institution.name as institution_name
            from data.reference
            inner join data.online_source on reference.idsource = online_source.identity
            inner join data.institution on online_source.identity = institution.identity
            where reference.idreference in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
