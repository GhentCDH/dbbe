<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

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

    public function getNewId(int $oldId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscripts_to_manuscript.identity as new_id
            from migration.manuscripts_to_manuscript
            where manuscripts_to_manuscript.old_id = ?',
            [$oldId]
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.manuscript on entity.identity = manuscript.identity'
        )->fetch();
    }

    /**
     * Get all manuscripts that are dependent on a specific region
     * @param  int   $regionId
     * @return array
     */
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

    /**
     * Get all manuscripts that are dependent on a specific region or one of its children
     * @param  int   $regionId
     * @return array
     */
    public function getDepIdsByRegionIdWithChildren(int $regionId): array
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
            where manreg.region_id in (
                WITH RECURSIVE rec (id, idparent) AS (
                    SELECT
                        r.identity,
                        r.parent_idregion
                    FROM data.region r

                    UNION ALL

                    SELECT
                        rec.id,
                        r.parent_idregion
                    FROM rec
                    INNER JOIN data.region r
                    ON r.identity = rec.idparent
                )
                SELECT id
                FROM rec
                WHERE rec.idparent = ? or rec.id = ?
            )',
            [
                $regionId,
                $regionId,
            ]
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

    /**
     * Get all manuscripts that are dependent on a specific content
     * @param  int   $contentId
     * @return array
     */
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

    /**
     * Get all manuscripts that are dependent on a specific content or one of its children
     * @param  int   $contentId
     * @return array
     */
    public function getDepIdsByContentIdWithChildren(int $contentId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.document_genre on manuscript.identity = document_genre.iddocument
            where document_genre.idgenre in (
                WITH RECURSIVE rec (id, idparent) AS (
                    SELECT
                        g.idgenre,
                        g.idparentgenre
                    FROM data.genre g

                    UNION ALL

                    SELECT
                        rec.id,
                        g.idparentgenre
                    FROM rec
                    INNER JOIN data.genre g
                    ON g.idgenre = rec.idparent
                )
                SELECT id
                FROM rec
                WHERE rec.idparent = ? or rec.id = ?
            )',
            [
                $contentId,
                $contentId,
            ]
        )->fetchAll();
    }

    public function getDepIdsByStatusId(int $statusId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.document_status on manuscript.identity = document_status.iddocument
            where document_status.idstatus = ?',
            [$statusId]
        )->fetchAll();
    }

    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manpers.manuscript_id
            from (
                select manuscript.identity as manuscript_id,
                    bibrole.idperson as person_id
                from data.manuscript
                inner join data.bibrole on manuscript.identity = bibrole.iddocument

                union

                select manuscript.identity as manuscript_id,
                    factoid.object_identity
                from data.manuscript
                inner join data.factoid on manuscript.identity = factoid.subject_identity
            ) as manpers
            where manpers.person_id = ?',
            [$personId]
        )->fetchAll();
    }

    public function getDepIdsByAcknowledgementId(int $acknowledgementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.document_acknowledgement on manuscript.identity = document_acknowledgement.iddocument
            where document_acknowledgement.idacknowledgement = ?',
            [$acknowledgementId]
        )->fetchAll();
    }

    public function getDepIdsByOccurrenceId(int $occurrenceId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.document_contains on manuscript.identity = document_contains.idcontainer
            inner join data.original_poem on document_contains.idcontent = original_poem.identity
            where original_poem.identity = ?',
            [$occurrenceId]
        )->fetchAll();
    }

    public function getDepIdsByRoleId(int $roleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.bibrole on manuscript.identity = bibrole.iddocument
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchAll();
    }

    public function getDepIdsByArticleId(int $articleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.reference on manuscript.identity = reference.idtarget
            inner join data.article on reference.idsource = article.identity
            where article.identity = ?',
            [$articleId]
        )->fetchAll();
    }

    public function getDepIdsByBookId(int $bookId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.reference on manuscript.identity = reference.idtarget
            inner join data.book on reference.idsource = book.identity
            where book.identity = ?',
            [$bookId]
        )->fetchAll();
    }

    public function getDepIdsByBookChapterId(int $bookChapterId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.reference on manuscript.identity = reference.idtarget
            inner join data.bookchapter on reference.idsource = bookchapter.identity
            where bookchapter.identity = ?',
            [$bookChapterId]
        )->fetchAll();
    }

    public function getDepIdsByOnlineSourceId(int $onlineSourceId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.reference on manuscript.identity = reference.idtarget
            inner join data.online_source on reference.idsource = online_source.identity
            where online_source.identity = ?',
            [$onlineSourceId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id
            from data.manuscript
            inner join data.entity_management on manuscript.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
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

    public function getOccurrencePersonRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                bibrole.iddocument as occurrence_id,
                bibrole.idperson as person_id,
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name,
                role.is_contributor_role as role_is_contributor_role,
                role.has_rank as role_has_rank
            from data.manuscript
            inner join data.document_contains on manuscript.identity = document_contains.idcontainer
            inner join data.bibrole on document_contains.idcontent = bibrole.iddocument
            inner join data.role on bibrole.idrole = role.idrole
            where manuscript.identity in (?)
            and (role.is_contributor_role is null or role.is_contributor_role = false)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
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

    public function getOccurrences(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                manuscript.identity as manuscript_id,
                document_contains.idcontent as occurrence_id
            from data.manuscript
            inner join data.document_contains on manuscript.identity = document_contains.idcontainer
            inner join data.original_poem on document_contains.idcontent = original_poem.identity
            inner join data.poem on document_contains.idcontent = poem.identity
            where manuscript.identity in (?)
            -- order by order,
            -- then folium start (sort as if it where numbers, but it is actually a text column),
            -- then folium start recto
            order by document_contains.order,
                NULLIF(regexp_replace(document_contains.folium_start, \'\\D\', \'\', \'g\'), \'\')::int,
                document_contains.folium_start_recto desc,
                NULLIF(regexp_replace(document_contains.folium_end, \'\\D\', \'\', \'g\'), \'\')::int,
                document_contains.folium_end_recto desc,
                poem.incipit
            ',
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
                status.status as status_name,
                status.type as status_type
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
        $this->beginTransaction();
        try {
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
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
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

    public function insertOrigin(int $manuscriptId, int $locationId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.factoid (subject_identity, idlocation, idfactoid_type)
            values (
                ?,
                ?,
                (select factoid_type.idfactoid_type from data.factoid_type where factoid_type.type = \'written\')
            )',
            [
                $manuscriptId,
                $locationId,
            ]
        );
    }

    public function updateOrigin(int $manuscriptId, int $locationId): int
    {
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

    public function deleteOrigin(int $manuscriptId): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.factoid
            using data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = \'written\'',
            [
                $manuscriptId,
            ]
        );
    }

    public function updateOccurrenceOrder(int $manuscriptId, array $occurrenceOrder): int
    {
        $this->beginTransaction();
        try {
            foreach ($occurrenceOrder as $orderItem) {
                $this->conn->executeUpdate(
                    'UPDATE data.document_contains
                    set "order" = ?
                    where document_contains.idcontainer = ?
                    and document_contains.idcontent = ?',
                    [
                        $orderItem['order'],
                        $manuscriptId,
                        $orderItem['occurrence_id'],
                    ]
                );
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return count($occurrenceOrder);
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

    public function delete(int $manuscriptId): int
    {
        $this->beginTransaction();
        try {
            // don't delete if this manuscript is used in document_contains
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
                'DELETE from data.document_title
                where document_title.iddocument = ?',
                [$manuscriptId]
            );
            $this->conn->executeUpdate(
                'DELETE from data.factoid
                where factoid.subject_identity = ?',
                [$manuscriptId]
            );
            $delete = $this->conn->executeUpdate(
                'DELETE from data.document
                where document.identity = ?',
                [$manuscriptId]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $delete;
    }
}
