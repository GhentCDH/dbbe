<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Service\DatabaseService\DatabaseService;

class ManuscriptService extends DatabaseService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript'
        )->fetchAll();
    }

    public function getContents(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                document_genre.idgenre as genre_id
            from data.manuscript
            inner join data.document_genre on manuscript.identity = document_genre.iddocument
            where manuscript.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getBibroles(array $ids, array $roles): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                bibrole.idperson as person_id,
                bibrole.type
            from data.manuscript
            inner join data.bibrole on manuscript.identity = bibrole.iddocument
            where manuscript.identity in (?)
            and bibrole.type in (?)',
            [
                $ids,
                $roles,
            ],
            [
                Connection::PARAM_INT_ARRAY,
                Connection::PARAM_STR_ARRAY,
            ]
        )->fetchAll();
    }

    public function getOccurrenceBibroles(array $ids, array $roles): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                bibrole.iddocument as occurrence_id,
                bibrole.idperson as person_id,
                bibrole.type
            from data.manuscript
            inner join data.document_contains on manuscript.identity = document_contains.idcontainer
            inner join data.bibrole on document_contains.idcontent = bibrole.iddocument
            where manuscript.identity in (?)
            and bibrole.type in (?)',
            [
                $ids,
                $roles,
            ],
            [
                Connection::PARAM_INT_ARRAY,
                Connection::PARAM_STR_ARRAY,
            ]
        )->fetchAll();
    }

    public function getRelatedPersons(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                factoid.subject_identity as manuscript_id,
                factoid.object_identity as person_id
            from data.manuscript
            inner join data.factoid on manuscript.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.person on factoid.object_identity = person.identity
            where manuscript.identity in (?)
            and type = \'related to\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getCompletionDates(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                factoid_merge.factoid_date as completion_date
            from data.manuscript
            inner join (
                select
                    factoid.subject_identity as factoid_identity,
                    factoid.date as factoid_date
                from data.factoid
                inner join data.factoid_type
                    on factoid.idfactoid_type = factoid_type.idfactoid_type
                        and factoid_type.type = \'completed at\'
            ) factoid_merge on manuscript.identity = factoid_merge.factoid_identity
            where manuscript.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOrigins(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                location.idinstitution as institution_id,
                location.idlocation as location_id,
                coalesce(institution.idregion, location.idregion) as region_id,
                institution.name as institution_name
            from data.manuscript
            inner join data.factoid on manuscript.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.location on factoid.idlocation = location.idlocation
            left join data.institution on location.idinstitution = institution.identity
            where manuscript.identity in (?)
            and factoid_type.type = \'written\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getBibliographies(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
            	manuscript.identity as manuscript_id,
                reference.idreference as reference_id,
	            coalesce(
                    book_merge.type::text,
                    article_merge.type::text,
                    book_chapter_merge.type::text,
                    online_source_merge.type::text
                ) as type
            from data.manuscript
            inner join data.reference on manuscript.identity = reference.idtarget
            left join (
            	select
            		book.identity as biblio_id,
            		\'book\' as type
            	from data.book
            ) book_merge on reference.idsource = book_merge.biblio_id
            left join (
            	select
            		article.identity as biblio_id,
            		\'article\' as type
            	from data.article
            ) article_merge on reference.idsource = article_merge.biblio_id
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
            where manuscript.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
    public function getDiktyons(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                global_id.identifier as diktyon_id
            from data.manuscript
            inner join data.global_id on manuscript.identity = global_id.idsubject
            inner join data.institution on global_id.idauthority = institution.identity
            where manuscript.identity in (?)
            and institution.name = \'Diktyon\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getComments(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                entity.public_comment,
                entity.private_comment
            from data.manuscript
            inner join data.entity on manuscript.identity = entity.identity
            where manuscript.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOccurrences(array $ids = null): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                document_contains.idcontent as occurrence_id
            from data.manuscript
            inner join data.document_contains on manuscript.identity = document_contains.idcontainer
            inner join data.original_poem on document_contains.idcontent = original_poem.identity
            where manuscript.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getIllustrateds(array $ids = null): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                document.is_illustrated as illustrated
            from data.manuscript
            inner join data.document on manuscript.identity = document.identity
            where manuscript.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function delContents(int $manuscriptId, array $contentIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.document_genre
            where document_genre.iddocument = ?
            and document_genre.idgenre in (?)',
            [
                $manuscriptId,
                $contentIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function addContent(int $manuscriptId, int $contentId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.document_genre (iddocument, idgenre)
            values (?, ?)',
            [
                $manuscriptId,
                $contentId,
            ]
        );
    }

    public function delBibroles(int $manuscriptId, string $role, array $personIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.bibrole
            where bibrole.iddocument = ?
            and bibrole.type = ?
            and bibrole.idperson in (?)',
            [
                $manuscriptId,
                $role,
                $personIds,
            ],
            [
                \PDO::PARAM_INT,
                \PDO::PARAM_STR,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function addBibrole(int $manuscriptId, string $role, int $personId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.bibrole (iddocument, type, idperson)
            values (?, ?, ?)',
            [
                $manuscriptId,
                $role,
                $personId,
            ]
        );
    }

    public function delRelatedPersons(int $manuscriptId, array $personIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.factoid
            where factoid.subject_identity = ?
            and factoid.object_identity in (?)',
            [
                $manuscriptId,
                $personIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function addRelatedPerson(int $manuscriptId, int $personId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.factoid (subject_identity, object_identity, idfactoid_type)
            values (
                ?,
                ?,
                (
                    select
                        factoid_type.idfactoid_type
                    from data.factoid_type
                    where factoid_type.type = \'related to\'
                )
            )',
            [
                $manuscriptId,
                $personId,
            ]
        );
    }

    public function insertCompletionDate(int $manuscriptId, string $completionDate): int
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
                    where factoid_type.type = \'completed at\'
                )
            )',
            [
                $manuscriptId,
                $completionDate,
            ]
        );
    }

    public function updateCompletionDate(int $manuscriptId, string $completionDate): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set date = ?
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = \'completed at\'',
            [
                $completionDate,
                $manuscriptId,
            ]
        );
    }

    public function updateOrigin(int $manuscriptId, int $locationId): int
    {
        // TODO: insert, update on conflict
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set idlocation = ?
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = \'written\'',
            [
                $locationId,
                $manuscriptId,
            ]
        );
    }

    public function upsertDiktyon(int $manuscriptId, int $diktyon): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.global_id (idauthority, idsubject, identifier)
            values (
                (
                    select institution.identity
                    from data.institution
                    where institution.name = \'Diktyon\'
                ),
                ?,
                ?
            )
            -- primary key constraint on idauthority, idsubject
            on conflict (idauthority, idsubject) do update
            set identifier = excluded.identifier',
            [$manuscriptId, $diktyon]
        );
    }

    public function deleteDiktyon(int $manuscriptId): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.global_id
            using data.institution
            where global_id.idauthority = institution.identity
            and institution.name = \'Diktyon\'
            and global_id.idsubject = ?',
            [$manuscriptId]
        );
    }

    public function updatePublicComment(int $manuscriptId, string $publicComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set public_comment = ?
            where entity.identity = ?',
            [$publicComment, $manuscriptId]
        );
    }

    public function updatePrivateComment(int $manuscriptId, string $privateComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set private_comment = ?
            where entity.identity = ?',
            [$privateComment, $manuscriptId]
        );
    }
}
