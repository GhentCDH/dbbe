<?php

namespace AppBundle\Service\DatabaseService;

class DeadLinkService extends DatabaseService
{
    public function getOccurrenceImages(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                array_to_json(array_agg(iddocument)) as occurrence_ids,
                document_image.idimage as image_id,
                image.filename
            from data.document_image
            inner join data.original_poem on document_image.iddocument = original_poem.identity
            inner join data.image on image.idimage = document_image.idimage
            where image.filename is not null
            group by document_image.idimage, image.filename'
        )->fetchAll();
    }

    public function getOccurrenceImageLinks(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                array_to_json(array_agg(iddocument)) as occurrence_ids,
                image.url
            from data.document_image
            inner join data.original_poem on document_image.iddocument = original_poem.identity
            inner join data.image on image.idimage = document_image.idimage
            where image.url is not null
            and image.url like \'http%\'
            group by image.url'
        )->fetchAll();
    }

    public function getOnlineSources(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                identity as online_source_id,
                url
            from data.online_source'
        )->fetchAll();
    }

    public function getOnlineSourceRelativeLinks(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference.idtarget as entity_id,
                reference.url,
	            coalesce(
                    manuscript_merge.type::text,
                    occurrence_merge.type::text,
                    type_merge.type::text,
                    person_merge.type::text
                ) as type
            from data.reference
            left join (
                select
                    manuscript.identity as entity_id,
                    \'manuscript\' as type
                from data.manuscript
            ) manuscript_merge on reference.idtarget = manuscript_merge.entity_id
            left join (
                select
                    original_poem.identity as entity_id,
                    \'occurrence\' as type
                from data.original_poem
            ) occurrence_merge on reference.idtarget = occurrence_merge.entity_id
            left join (
                select
                    reconstructed_poem.identity as entity_id,
                    \'type\' as type
                from data.reconstructed_poem
            ) type_merge on reference.idtarget = type_merge.entity_id
            left join (
                select
                    person.identity as entity_id,
                    \'person\' as type
                from data.person
            ) person_merge on reference.idtarget = person_merge.entity_id
            where reference.url is not null'
        )->fetchAll();
    }

    public function getIdentifierLinks(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                global_id.idsubject as entity_id,
                global_id.identifier as identification,
                identifier.system_name,
                identifier.link,
	            coalesce(
                    manuscript_merge.type::text,
                    occurrence_merge.type::text,
                    type_merge.type::text,
                    person_merge.type::text,
                    article_merge.type::text,
                    book_merge.type::text,
                    book_chapter_merge.type::text
                ) as type
            from data.global_id
            inner join data.identifier on global_id.idauthority = ANY(identifier.ids)
            left join (
                select
                    manuscript.identity as entity_id,
                    \'manuscript\' as type
                from data.manuscript
            ) manuscript_merge on global_id.idsubject = manuscript_merge.entity_id
            left join (
                select
                    original_poem.identity as entity_id,
                    \'occurrence\' as type
                from data.original_poem
            ) occurrence_merge on global_id.idsubject = occurrence_merge.entity_id
            left join (
                select
                    reconstructed_poem.identity as entity_id,
                    \'type\' as type
                from data.reconstructed_poem
            ) type_merge on global_id.idsubject = type_merge.entity_id
            left join (
                select
                    person.identity as entity_id,
                    \'person\' as type
                from data.person
            ) person_merge on global_id.idsubject = person_merge.entity_id
            left join (
                select
                    article.identity as entity_id,
                    \'article\' as type
                from data.article
            ) article_merge on global_id.idsubject = article_merge.entity_id
            left join (
                select
                    book.identity as entity_id,
                    \'book\' as type
                from data.book
            ) book_merge on global_id.idsubject = book_merge.entity_id
            left join (
                select
                    bookchapter.identity as entity_id,
                    \'book_chapter\' as type
                from data.bookchapter
            ) book_chapter_merge on global_id.idsubject = book_chapter_merge.entity_id
            where identifier.link is not null'
        )->fetchAll();
    }
}