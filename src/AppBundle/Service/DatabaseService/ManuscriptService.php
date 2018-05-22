<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class ManuscriptService extends DocumentService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript'
        )->fetchAll();
    }

    public function getDepIdsByRegionId(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manreg.manuscript_id
            from (
                select
                    manuscript.identity as manuscript_id,
                    region.identity as region_id
                from data.manuscript
                inner join data.located_at on manuscript.identity = located_at.iddocument
                inner join data.location on located_at.idlocation = location.idlocation
                left join data.fund on location.idfund = fund.idfund
                left join data.institution on coalesce(location.idinstitution, fund.idlibrary) = institution.identity
                inner join data.region on coalesce(location.idregion, institution.idregion) = region.identity

                union

                select
                    manuscript.identity as manuscript_id,
                    region.identity as region_id
                from data.manuscript
                inner join data.factoid on manuscript.identity = factoid.subject_identity
                inner join data.location on factoid.idlocation = location.idlocation
                left join data.institution on location.idinstitution = institution.identity
                inner join data.region on coalesce(location.idregion, institution.idregion) = region.identity
            ) as manreg
            where manreg.region_id = ?',
            [$regionId]
        )->fetchAll();
    }

    public function getDepIdsByInstitutionId(int $institutionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                maninst.manuscript_id
            from (
                select
                    manuscript.identity as manuscript_id,
                    institution.identity as institution_id
                from data.manuscript
                inner join data.located_at on manuscript.identity = located_at.iddocument
                inner join data.location on located_at.idlocation = location.idlocation
                left join data.fund on location.idfund = fund.idfund
                inner join data.institution on coalesce(location.idinstitution, fund.idlibrary) = institution.identity

                union

                select
                    manuscript.identity as manuscript_id,
                    institution.identity as institution_id
                from data.manuscript
                inner join data.factoid on manuscript.identity = factoid.subject_identity
                inner join data.location on location.idlocation = factoid.idlocation
                inner join data.institution on location.idinstitution = institution.identity
            ) as maninst
            where maninst.institution_id = ?',
            [$institutionId]
        )->fetchAll();
    }

    public function getDepIdsByCollectionId(int $collectionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                mancoll.manuscript_id
            from (
                select
                    manuscript.identity as manuscript_id,
                    location.idfund as collection_id
                from data.manuscript
                inner join data.located_at on manuscript.identity = located_at.iddocument
                inner join data.location on location.idlocation = located_at.idlocation

                union

                select
                    manuscript.identity as manuscript_id,
                    location.idfund as collection_id
                from data.manuscript
                inner join data.factoid on manuscript.identity = factoid.subject_identity
                inner join data.location on location.idlocation = factoid.idlocation
            ) as mancoll
            where mancoll.collection_id = ?',
            [$collectionId]
        )->fetchAll();
    }

    public function getDepIdsByContentId(int $contentId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.document_genre on manuscript.identity = document_genre.iddocument
            where document_genre.idgenre = ?',
            [$contentId]
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

    public function getOrigins(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                factoid.idlocation as location_id
            from data.manuscript
            inner join data.factoid on manuscript.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where manuscript.identity in (?)
            and factoid_type.type = \'written\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDiktyons(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                diktyon.identifier as diktyon_id
            from data.manuscript
            left join (
                select global_id.idsubject, global_id.identifier
                from data.global_id
                inner join data.institution
                    on global_id.idauthority = institution.identity
                    and institution.name = \'Diktyon\'
            ) as diktyon on manuscript.identity = diktyon.idsubject
            where manuscript.identity in (?)',
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

    public function getOccurrences(array $ids): array
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

    public function getStatuses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_status.iddocument as manuscript_id,
                status.idstatus as status_id,
                status.status as status_name
            from data.document_status
            inner join data.status on document_status.idstatus = status.idstatus
            where document_status.iddocument in (?)
            and status.type = \'manuscript\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getIllustrateds(array $ids): array
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

    public function insert(): int
    {
        // Set search_path for trigger ensure_manuscript_has_document
        $this->conn->exec('SET SEARCH_PATH TO data');
        $this->conn->executeUpdate(
            'INSERT INTO data.manuscript default values'
        );
        $manuscriptId = $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            order by identity desc
            limit 1'
        )->fetch()['manuscript_id'];
        return $manuscriptId;
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
            [
                $manuscriptId,
                $diktyon,
            ]
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
            [
                $manuscriptId,
            ]
        );
    }

    public function updatePublicComment(int $manuscriptId, string $publicComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set public_comment = ?
            where entity.identity = ?',
            [
                $publicComment,
                $manuscriptId,
            ]
        );
    }

    public function updatePrivateComment(int $manuscriptId, string $privateComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set private_comment = ?
            where entity.identity = ?',
            [
                $privateComment,
                $manuscriptId,
            ]
        );
    }

    public function updateIllustrated(int $manuscriptId, bool $illustrated): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document
            set is_illustrated = ?
            where document.identity = ?',
            [
                $illustrated ? 'TRUE': 'FALSE',
                $manuscriptId,
            ]
        );
    }

    public function updatePublic(int $manuscriptId, bool $public): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set public = ?
            where entity.identity = ?',
            [
                $public ? 'TRUE': 'FALSE',
                $manuscriptId,
            ]
        );
    }

    public function delete(int $manuscriptId): int
    {
        // don't delete if this region is used in document_contains
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.document_contains
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            where manuscript.identity = ?',
            [$manuscriptId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This manuscript has dependencies.');
        }
        // Set search_path for triggers
        $this->conn->exec('SET SEARCH_PATH TO data');
        $this->conn->executeUpdate(
            'DELETE from data.factoid
            where factoid.subject_identity = ?',
            [$manuscriptId]
        );
        return $this->conn->executeUpdate(
            'DELETE from data.manuscript
            where manuscript.identity = ?',
            [$manuscriptId]
        );
    }
}
