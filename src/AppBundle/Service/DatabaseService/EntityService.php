<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class EntityService extends DatabaseService
{
    public function getPublics(array $ids = null): array
    {
        return $this->conn->executeQuery(
            'SELECT
                entity.identity as entity_id,
                entity.public
            from data.entity
            where entity.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getCreatedAndModifiedDates(array $ids = null): array
    {
        return $this->conn->executeQuery(
            'SELECT
                entity.identity as entity_id,
                entity.created,
                entity.modified
            from data.entity
            where entity.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getComments(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                entity.identity as entity_id,
                entity.public_comment,
                entity.private_comment
            from data.entity
            where entity.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getIdentifications(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                global_id.idsubject as entity_id,
                identifier.ididentifier as identifier_id,
                identifier.system_name,
                identifier.name,
                identifier.is_primary,
                identifier.link,
                identifier.link_type,
                array_to_json(identifier.ids) as ids,
                identifier.regex,
                identifier.description,
                identifier.extra,
                array_to_json(array_agg(global_id.identifier ORDER BY array_position(identifier.ids, global_id.idauthority))) as identifications,
                array_to_json(array_agg(global_id.volume ORDER BY array_position(identifier.ids, global_id.idauthority))) as identification_volumes,
                array_to_json(array_agg(global_id.extra ORDER BY array_position(identifier.ids, global_id.idauthority))) as identification_extras
            from data.global_id
            inner join data.identifier on global_id.idauthority = ANY(identifier.ids)
            where global_id.idsubject in (?)
            group by global_id.idsubject, identifier.ididentifier
            order by identifier.order',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getInverseIdentifications(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                global_id.idauthority as identifier_id,
                global_id.idsubject as entity_id,
	            coalesce(
                    manuscript_merge.type::text,
                    occurrence_merge.type::text,
                    type_merge.type::text,
                    person_merge.type::text,
                    article_merge.type::text,
                    book_merge.type::text,
                    book_chapter_merge.type::text,
                    region_merge.type::text
                ) as type
            from data.global_id
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
            left join (
                select
                    region.identity as entity_id,
                    \'region\' as type
                from data.region
            ) region_merge on global_id.idsubject = region_merge.entity_id
            where global_id.idauthority in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getBibliographies(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference.idtarget as entity_id,
                reference.idreference as reference_id
            from data.reference
            where reference.idtarget in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getInverseBibliographies(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference.idsource as biblio_id,
                reference.idtarget as entity_id,
	            coalesce(
                    manuscript_merge.type::text,
                    occurrence_merge.type::text,
                    type_merge.type::text,
                    person_merge.type::text,
                    translation_merge.type::text
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
            left join (
                select
                    translation.identity as entity_id,
                    \'translation\' as type
                from data.translation
            ) translation_merge on reference.idtarget = translation_merge.entity_id
            where reference.idsource in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getManagements(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                entity_management.identity as entity_id,
                entity_management.idmanagement as management_id,
                management.name as management_name
            from data.entity_management
            inner join data.management on entity_management.idmanagement = management.id
            where entity_management.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function updatePublic(int $entityId, bool $public): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set public = ?
            where entity.identity = ?',
            [
                $public ? 'TRUE': 'FALSE',
                $entityId,
            ]
        );
    }

    public function insertDate(int $entityId, string $type, string $date): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.factoid (subject_identity, date, idfactoid_type)
            values (
                ?,
                ?,
                (
                    select
                        factoid_type.idfactoid_type
                    from data.factoid_type
                    where factoid_type.type = ?
                )
            )',
            [
                $entityId,
                $date,
                $type,
            ]
        );
    }

    public function updateDate(int $entityId, string $type, string $date): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set date = ?, interval = null
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = ?',
            [
                $date,
                $entityId,
                $type,
            ]
        );
    }

    public function deleteDateOrInterval(int $entityId, string $type): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.factoid
            using data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = ?',
            [
                $entityId,
                $type,
            ]
        );
    }

    public function insertInterval(int $entityId, string $type, string $interval): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.factoid (subject_identity, interval, idfactoid_type)
            values (
                ?,
                ?,
                (
                    select
                        factoid_type.idfactoid_type
                    from data.factoid_type
                    where factoid_type.type = ?
                )
            )',
            [
                $entityId,
                $interval,
                $type,
            ]
        );
    }

    public function updateInterval(int $entityId, string $type, string $interval): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set date = null, interval = ?
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = ?',
            [
                $interval,
                $entityId,
                $type,
            ]
        );
    }

    public function updatePublicComment(int $entityId, string $publicComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set public_comment = ?
            where entity.identity = ?',
            [
                $publicComment,
                $entityId,
            ]
        );
    }

    public function updatePrivateComment(int $entityId, string $privateComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set private_comment = ?
            where entity.identity = ?',
            [
                $privateComment,
                $entityId,
            ]
        );
    }

    public function delIdentification(int $entityId, int $identifierId, int $volume)
    {
        // Postgresql array indices start with 1
        return $this->conn->executeUpdate(
            'DELETE from data.global_id
            using data.identifier
            where global_id.idsubject = ?
            and global_id.idauthority = identifier.ids[?]
            and identifier.ididentifier = ?',
            [
                $entityId,
                $volume,
                $identifierId,
            ]
        );
    }

    public function upsertIdentification(
        int $entityId,
        int $identifierId,
        string $identification,
        string $extra = null,
        int $volume = null
    ): int {
        // Postgresql array indices start with 1
        return $this->conn->executeUpdate(
            'INSERT into data.global_id (idauthority, idsubject, identifier, extra, volume)
            VALUES (
                (
                    select identifier.ids[?]
                    from data.identifier
                    where identifier.ididentifier = ?
                ),
                ?,
                ?,
                ?,
                ?
            )
            -- primary key constraint on idauthority, idsubject
            on conflict (idauthority, idsubject) do update
            set identifier = excluded.identifier,
                extra = excluded.extra,
                volume = excluded.volume',
            [
                $volume == null ? 1 : $volume,
                $identifierId,
                $entityId,
                $identification,
                $extra,
                $volume,
            ]
        );
    }

    public function addManagement(int $id, int $managementId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.entity_management (identity, idmanagement)
            values (?, ?)',
            [
                $id,
                $managementId,
            ]
        );
    }

    public function delManagements(int $id, array $managementIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.entity_management
            where identity = ?
            and idmanagement in (?)',
            [
                $id,
                $managementIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }
}
