<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

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
                case when reference.page_start is null then reference.temp_page_removeme else null end as raw_pages,
                reference.url as rel_url,
                reference.idreference_type as reference_type_id,
                reference.source_remark,
                reference.image,
	            coalesce(
                    article_merge.type::text,
                    blog_post_merge.type::text,
                    book_merge.type::text,
                    book_chapter_merge.type::text,
                    online_source_merge.type::text,
                    phd_merge.type::text
                ) as bib_type
            from data.reference
            left join (
                select
                    article.identity as biblio_id,
                    \'article\' as type
                from data.article
            ) article_merge on reference.idsource = article_merge.biblio_id
            left join (
                select
                    blog_post.identity as biblio_id,
                    \'blog_post\' as type
                from data.blog_post
            ) blog_post_merge on reference.idsource = blog_post_merge.biblio_id
            left join (
                select
                    book.identity as biblio_id,
                    \'book\' as type
                from data.book
            ) book_merge on reference.idsource = book_merge.biblio_id
            left join (
                select
                    bookchapter.identity as biblio_id,
                    \'book_chapter\' as type
                from data.bookchapter
            ) book_chapter_merge on reference.idsource = book_chapter_merge.biblio_id
            left join (
                select
                    online_source.identity as biblio_id,
                    \'online_source\' as type
                from data.online_source
            ) online_source_merge on reference.idsource = online_source_merge.biblio_id
            left join (
                select
                    phd.identity as biblio_id,
                    \'phd\' as type
                from data.phd
            ) phd_merge on reference.idsource = phd_merge.biblio_id
            where reference.idreference in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function insert(
        int $targetId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $url = null,
        int $referenceTypeId = null,
        string $image = null
    ): int {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.reference (
                    idtarget,
                    idsource,
                    page_start,
                    page_end,
                    url,
                    idreference_type,
                    image
                )
                values (?, ?, ?, ?, ?, ?, ?)',
                [
                    $targetId,
                    $sourceId,
                    $startPage,
                    $endPage,
                    $url,
                    $referenceTypeId,
                    $image,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    reference.idreference as reference_id
                from data.reference
                order by idreference desc
                limit 1'
            )->fetch()['reference_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    public function update(
        int $referenceId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $rawPages = null,
        string $url = null,
        int $referenceTypeId = null,
        string $image = null
    ): int {
        return $this->conn->executeUpdate(
            'UPDATE data.reference
            set
                idsource = ?,
                page_start = ?,
                page_end = ?,
                temp_page_removeme = ?,
                url = ?,
                idreference_type = ?,
                image = ?
            where idreference = ?',
            [
                $sourceId,
                $startPage,
                $endPage,
                $rawPages,
                $url,
                $referenceTypeId,
                $image,
                $referenceId,
            ]
        );
    }

    public function deleteMultiple(array $ids): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.reference
            where reference.idreference in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        );
    }
}
