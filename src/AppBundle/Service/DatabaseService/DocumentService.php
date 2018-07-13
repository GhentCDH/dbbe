<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class DocumentService extends EntityService
{
    public function getCompletionDates(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document.identity as document_id,
                factoid_merge.factoid_date as completion_date
            from data.document
            inner join (
                select
                    factoid.subject_identity as factoid_identity,
                    factoid.date as factoid_date
                from data.factoid
                inner join data.factoid_type
                    on factoid.idfactoid_type = factoid_type.idfactoid_type
                        and factoid_type.type = \'completed at\'
            ) factoid_merge on document.identity = factoid_merge.factoid_identity
            where document.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getBibliographies(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
            	document.identity as document_id,
                reference.idreference as reference_id,
	            coalesce(
                    book_merge.type::text,
                    article_merge.type::text,
                    book_chapter_merge.type::text,
                    online_source_merge.type::text
                ) as type
            from data.document
            inner join data.reference on document.identity = reference.idtarget
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
            where document.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getPersonRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.iddocument as manuscript_id,
                bibrole.idperson as person_id,
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name
            from data.bibrole
            inner join data.role on bibrole.idrole = role.idrole
            where bibrole.iddocument in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function addPersonRole(int $documentId, int $roleId, int $personId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.bibrole (iddocument, idrole, idperson)
            values (
                ?,
                ?,
                ?
            )',
            [
                $documentId,
                $roleId,
                $personId,
            ]
        );
    }

    public function delPersonRole(int $documentId, int $roleId, array $personIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.bibrole
            where bibrole.iddocument = ?
            and bibrole.idrole = ?
            and bibrole.idperson in (?)',
            [
                $documentId,
                $roleId,
                $personIds,
            ],
            [
                \PDO::PARAM_INT,
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }
}
