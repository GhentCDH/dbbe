<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class OccurrenceService extends PoemService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem'
        )->fetchAll();
    }

    public function getNewId(int $oldId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occurrence_to_entity.identity as new_id
            from migration.occurrence_to_entity
            where occurrence_to_entity.old_id = ?',
            [$oldId]
        )->fetchAll();
    }

    public function getDepIdsByManuscriptId(int $manuscriptId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.document_contains on original_poem.identity = document_contains.idcontent
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            where manuscript.identity = ?',
            [$manuscriptId]
        )->fetchAll();
    }

    public function getDepIdsByStatusId(int $statusId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.document_status on original_poem.identity = document_status.iddocument
            where document_status.idstatus = ?',
            [$statusId]
        )->fetchAll();
    }

    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occpers.occurrence_id
            from (
                select
                    original_poem.identity as occurrence_id,
                    bibrole.idperson as person_id
                from data.original_poem
                inner join data.bibrole on original_poem.identity = bibrole.iddocument

                union

                select
                    original_poem.identity as occurrence_id,
                    person.identity as person_id
                from data.original_poem
                inner join data.factoid on original_poem.identity = factoid.object_identity
                inner join data.person on factoid.subject_identity = person.identity
            ) as occpers
            where occpers.person_id = ?',
            [$personId]
        )->fetchAll();
    }

    public function getDepIdsByMeterId(int $meterId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.poem_meter on original_poem.identity = poem_meter.idpoem
            where poem_meter.idmeter = ?',
            [$meterId]
        )->fetchAll();
    }

    public function getDepIdsByGenreId(int $genreId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.document_genre on original_poem.identity = document_genre.iddocument
            where document_genre.idgenre = ?',
            [$genreId]
        )->fetchAll();
    }

    public function getDepIdsByKeywordId(int $keywordId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.factoid on original_poem.identity = factoid.object_identity
            where factoid.subject_identity = ?',
            [$keywordId]
        )->fetchAll();
    }

    public function getDepIdsByAcknowledgementId(int $acknowledgementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.document_acknowledgement on original_poem.identity = document_acknowledgement.iddocument
            where document_acknowledgement.idacknowledgement = ?',
            [$acknowledgementId]
        )->fetchAll();
    }

    public function getDepIdsByTypeId(int $typeId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.factoid on original_poem.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.reconstructed_poem on factoid.object_identity = reconstructed_poem.identity
            where reconstructed_poem.identity = ?
            and factoid_type.type = \'reconstruction of\'',
            [$typeId]
        )->fetchAll();
    }

    public function getDepIdsByRoleId(int $roleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.bibrole on original_poem.identity = bibrole.iddocument
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchAll();
    }

    public function getDepIdsByArticleId(int $articleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.reference on original_poem.identity = reference.idtarget
            inner join data.article on reference.idsource = article.identity
            where article.identity = ?',
            [$articleId]
        )->fetchAll();
    }

    public function getDepIdsByBookId(int $bookId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.reference on original_poem.identity = reference.idtarget
            inner join data.book on reference.idsource = book.identity
            where book.identity = ?',
            [$bookId]
        )->fetchAll();
    }

    public function getDepIdsByBookChapterId(int $bookChapterId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.reference on original_poem.identity = reference.idtarget
            inner join data.bookchapter on reference.idsource = bookchapter.identity
            where bookchapter.identity = ?',
            [$bookChapterId]
        )->fetchAll();
    }

    public function getDepIdsByOnlineSourceId(int $onlineSourceId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.reference on original_poem.identity = reference.idtarget
            inner join data.online_source on reference.idsource = online_source.identity
            where online_source.identity = ?',
            [$onlineSourceId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem
            inner join data.entity_management on original_poem.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
        )->fetchAll();
    }

    public function getLocations(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                document_contains.folium_start,
                document_contains.folium_start_recto,
                document_contains.folium_end,
                document_contains.folium_end_recto,
                document_contains.unsure,
                document_contains.general_location,
                document_contains.alternative_folium_start,
                document_contains.alternative_folium_start_recto,
                document_contains.alternative_folium_end,
                document_contains.alternative_folium_end_recto,
                document_contains.idcontainer as manuscript_id
            from data.original_poem
            inner join data.document_contains on original_poem.identity = document_contains.idcontent
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getVerses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem_verse.idoriginal_poem as occurrence_id,
                original_poem_verse.id as verse_id,
                original_poem_verse.idgroup as group_id,
                original_poem_verse.verse,
                original_poem_verse.order
            from data.original_poem_verse
            where original_poem_verse.idoriginal_poem in (?)
            order by "order"',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getPrevIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occurrence_to_entity.identity as document_id,
                occurrence_to_entity.old_id as prev_id
            from migration.occurrence_to_entity
            where occurrence_to_entity.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getStatuses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_status.iddocument as occurrence_id,
                status.idstatus as status_id,
                status.status as status_name,
                status.type as status_type
            from data.document_status
            inner join data.status on document_status.idstatus = status.idstatus
            where document_status.iddocument in (?)
            and status.type in (
                \'occurrence_text\',
                \'occurrence_record\',
                \'occurrence_divided\',
                \'occurrence_source\'
            )',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getRelatedOccurrences(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT * from (
                -- verse variants
                select
                count(a.id) as count,
                b.idoriginal_poem as related_occurrence_id,
                poem.incipit as related_occurrence_incipit
                from data.original_poem_verse a
                inner join data.original_poem_verse b on a.idgroup = b.idgroup
                inner join data.poem on b.idoriginal_poem = poem.identity
                where a.idoriginal_poem in (?)
                and b.idoriginal_poem <> a.idoriginal_poem
                group by a.idoriginal_poem, b.idoriginal_poem, poem.incipit

                union

                -- common type, no verse variants
                select
                0 as count,
                fb.subject_identity as related_occurrence_id,
                poem.incipit as related_occurrence_incipit
                from data.factoid fa
                inner join data.factoid_type fta on fa.idfactoid_type = fta.idfactoid_type
                inner join data.factoid fb on fa.object_identity = fb.object_identity
                inner join data.factoid_type ftb on fa.idfactoid_type = ftb.idfactoid_type
                -- make sure we only retrieve occurrences
                inner join data.original_poem opb on fb.subject_identity = opb.identity
                inner join data.poem on fb.subject_identity = poem.identity
                where fa.subject_identity in (?)
                and fta.type = \'reconstruction of\'
                and ftb.type = \'reconstruction of\'
                and fb.subject_identity <> fa.subject_identity
                and fb.subject_identity not in (
                    select
                    b.idoriginal_poem
                    from data.original_poem_verse a
                    inner join data.original_poem_verse b on a.idgroup = b.idgroup
                    where a.idoriginal_poem in (?)
                    and b.idoriginal_poem <> a.idoriginal_poem
                    group by a.idoriginal_poem, b.idoriginal_poem
                )
            ) as relocc
            order by count desc, related_occurrence_incipit',
            [
                $ids,
                $ids,
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
                Connection::PARAM_INT_ARRAY,
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getTypes(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                factoid.object_identity as type_id
            from data.original_poem
            inner join data.factoid on original_poem.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where original_poem.identity in (?)
            and factoid_type.type = \'reconstruction of\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getPaleographicalInfos(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                original_poem.paleographical_info
            from data.original_poem
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getContextualInfos(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                document_contains.contextual_info
            from data.original_poem
            inner join data.document_contains on original_poem.identity = document_contains.idcontent
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getImages(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                image.idimage as image_id,
                image.filename,
                image.url,
                image.is_private
            from data.original_poem
            inner join data.document_image on original_poem.identity = document_image.iddocument
            inner join data.image on document_image.idimage = image.idimage
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function insert(int $manuscriptId, string $incipit): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_original_poem_has_identity
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.original_poem default values'
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    original_poem.identity as occurrence_id
                from data.original_poem
                order by identity desc
                limit 1'
            )->fetch()['occurrence_id'];
            $this->conn->executeUpdate(
                'INSERT INTO data.document_contains (idcontainer, idcontent) values (?, ?)',
                [
                    $manuscriptId,
                    $id,
                ]
            );
            $this->conn->executeUpdate(
                'UPDATE data.poem
                set incipit = ?
                where identity = ?',
                [
                    $incipit,
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
     * @param  int $manuscriptId
     * @return int
     */
    public function updateManuscript(int $id, int $manuscriptId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set idcontainer = ?
            where document_contains.idcontent = ?',
            [
                $manuscriptId,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $foliumStart
     * @return int
     */
    public function updateFoliumStart(int $id, string $foliumStart): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set folium_start = ?
            where document_contains.idcontent = ?',
            [
                $foliumStart,
                $id,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $foliumStartRecto
     * @return int
     */
    public function updateFoliumStartRecto(int $id, bool $foliumStartRecto): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set folium_start_recto = ?
            where document_contains.idcontent = ?',
            [
                $foliumStartRecto ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $foliumEnd
     * @return int
     */
    public function updateFoliumEnd(int $id, string $foliumEnd): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set folium_end = ?
            where document_contains.idcontent = ?',
            [
                $foliumEnd,
                $id,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $foliumEndRecto
     * @return int
     */
    public function updateFoliumEndRecto(int $id, bool $foliumEndRecto): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set folium_end_recto = ?
            where document_contains.idcontent = ?',
            [
                $foliumEndRecto ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $unsure
     * @return int
     */
    public function updateUnsure(int $id, bool $unsure): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set unsure = ?
            where document_contains.idcontent = ?',
            [
                $unsure ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $generalLocation
     * @return int
     */
    public function updateGeneralLocation(int $id, string $generalLocation): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set general_location = ?
            where document_contains.idcontent = ?',
            [
                $generalLocation,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $alternativeFoliumStart
     * @return int
     */
    public function updateAlternativeFoliumStart(int $id, string $alternativeFoliumStart): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set alternative_folium_start = ?
            where document_contains.idcontent = ?',
            [
                $alternativeFoliumStart,
                $id,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $alternativeFoliumStartRecto
     * @return int
     */
    public function updateAlternativeFoliumStartRecto(int $id, bool $alternativeFoliumStartRecto): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set alternative_folium_start_recto = ?
            where document_contains.idcontent = ?',
            [
                $alternativeFoliumStartRecto ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $alternativeFoliumEnd
     * @return int
     */
    public function updateAlternativeFoliumEnd(int $id, string $alternativeFoliumEnd): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set alternative_folium_end = ?
            where document_contains.idcontent = ?',
            [
                $alternativeFoliumEnd,
                $id,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $alternativeFoliumEndRecto
     * @return int
     */
    public function updateAlternativeFoliumEndRecto(int $id, bool $alternativeFoliumEndRecto): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set alternative_folium_end_recto = ?
            where document_contains.idcontent = ?',
            [
                $alternativeFoliumEndRecto ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $typeId
     * @return int
     */
    public function addType(int $id, int $typeId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.factoid (subject_identity, object_identity, idfactoid_type)
            values (
                ?,
                ?,
                (
                    select
                        factoid_type.idfactoid_type
                    from data.factoid_type
                    where factoid_type.type = \'reconstruction of\'
                )
            )',
            [
                $id,
                $typeId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $typeIds
     * @return int
     */
    public function delTypes(int $id, array $typeIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.factoid
            using data.factoid_type
            where factoid.subject_identity = ?
            and factoid.object_identity in (?)
            and factoid_type.type = \'reconstruction of\'',
            [
                $id,
                $typeIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $paleographicalInfo
     * @return int
     */
    public function updatePaleographicalInfo(int $id, string $paleographicalInfo): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.original_poem
            set paleographical_info = ?
            where identity = ?',
            [
                $paleographicalInfo,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $contextualInfo
     * @return int
     */
    public function updateContextualInfo(int $id, string $contextualInfo): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_contains
            set contextual_info = ?
            where idcontent = ?',
            [
                $contextualInfo,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $imageId
     * @return int
     */
    public function addImage(int $id, int $imageId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.document_image (iddocument, idimage)
            values (?, ?)',
            [
                $id,
                $imageId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $imageIds
     * @return int
     */
    public function delImages(int $id, array $imageIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.document_image
            where iddocument = ?
            and idimage in (?)',
            [
                $id,
                $imageIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function delete(int $id): int
    {
        $this->beginTransaction();
        try {
            // don't delete if this occurrence is used as based on
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid.object_identity = ?
                and factoid_type.type = \'based on\'',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This occurrence has dependencies.');
            }
            // Set search_path for triggers
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'DELETE from data.factoid
                using factoid_type
                where factoid.subject_identity = ?
                or (
                    factoid.object_identity = ?
                    and factoid.idfactoid_type = factoid_type.idfactoid_type
                    and factoid_type.type = \'subject of\'
                )',
                [
                    $id,
                    $id,
                ]
            );
            $this->conn->executeUpdate(
                'DELETE from data.original_poem_verse
                where original_poem_verse.idoriginal_poem = ?',
                [$id]
            );
            $delete = $this->conn->executeUpdate(
                'DELETE from data.document
                where document.identity = ?',
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
