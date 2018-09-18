<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class OccurrenceService extends DocumentService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                original_poem.identity as occurrence_id
            from data.original_poem'
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

    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occpers.occurrence_id
            from (
                SELECT
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
            inner join data.poem on original_poem.identity = poem.identity
            inner join data.poem_meter on poem.identity = poem_meter.idpoem
            where poem_meter.idmeter = ?',
            [$meterId]
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

    public function getIncipits(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                poem.incipit
            from data.original_poem
            inner join data.poem on original_poem.identity = poem.identity
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getTitles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_title.iddocument as occurrence_id,
                document_title.title
            from data.document_title
            where document_title.iddocument in (?)',
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

    public function getMeters(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                meter.idmeter as meter_id,
                meter.name as meter_name
                from data.original_poem
            inner join data.poem_meter on original_poem.identity = poem_meter.idpoem
            inner join data.meter on poem_meter.idmeter = meter.idmeter
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getSubjects(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                person.identity as person_id,
                keyword.identity as keyword_id
            from data.original_poem
            inner join data.factoid on original_poem.identity = factoid.object_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            left join data.person on factoid.subject_identity = person.identity
            left join data.keyword on factoid.subject_identity = keyword.identity
            where original_poem.identity in (?)
            and factoid_type.type = \'subject of\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getGenres(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                genre.idgenre as genre_id,
                genre.genre as genre_name
            from data.original_poem
            inner join data.document_genre on original_poem.identity = document_genre.iddocument
            inner join data.genre on document_genre.idgenre = genre.idgenre
            where original_poem.identity in (?)',
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
                status.type
            from data.document_status
            inner join data.status on document_status.idstatus = status.idstatus
            where document_status.iddocument in (?)
            and status.type in (\'occurrence_text\', \'occurrence_record\')',
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
                b.idoriginal_poem as related_occurrence_id
                from data.original_poem_verse a
                inner join data.original_poem_verse b on a.idgroup = b.idgroup
                where a.idoriginal_poem in (?)
                and b.idoriginal_poem <> a.idoriginal_poem
                group by a.idoriginal_poem, b.idoriginal_poem

                union

                -- common type, no verse variants
                select
                0 as count,
                fb.subject_identity as related_occurrence_id
                from data.factoid fa
                inner join data.factoid_type fta on fa.idfactoid_type = fta.idfactoid_type
                inner join data.factoid fb on fa.object_identity = fb.object_identity
                inner join data.factoid_type ftb on fa.idfactoid_type = ftb.idfactoid_type
                inner join data.original_poem opb on fb.subject_identity = opb.identity
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
            order by count desc',
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

    public function getNumberOfVerses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                poem.verses
            from data.original_poem
            inner join data.poem on original_poem.identity = poem.identity
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

    public function insert(int $manuscriptId): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_original_poem_has_identity
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.original_poem default values'
            );
            $occurrenceId = $this->conn->executeQuery(
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
                    $occurrenceId,
                ]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $occurrenceId;
    }

    /**
     * @param  int    $id
     * @param  string $incipit
     * @return int
     */
    public function updateIncipit(int $id, string $incipit): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.poem
            set incipit = ?
            where poem.identity = ?',
            [
                $incipit,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $title
     * @return int
     */
    public function updateTitle(int $id, string $title): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_title
            set title = ?
            where document_title.iddocument = ?',
            [
                $title,
                $id,
            ]
        );
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
     * @param  int $numberOfVerses
     * @return int
     */
    public function updateNumberOfVerses(int $id, int $numberOfVerses): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.poem
            set verses = ?
            where poem.identity = ?',
            [
                $numberOfVerses,
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
     * @param  int $id
     * @param  int $meterId
     * @return int
     */
    public function insertMeter(int $id, int $meterId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.poem_meter (idpoem, idmeter)
            values (
                ?,
                ?
            )',
            [
                $id,
                $meterId,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $meterId
     * @return int
     */
    public function updateMeter(int $id, int $meterId): int
    {
        return $this->conn->executeUpdate(
            'Update data.poem_meter
            set idmeter = ?
            where idpoem = ?',
            [
                $meterId,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @return int
     */
    public function deleteMeter(int $id): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.poem_meter
            where idpoem = ?',
            [
                $id,
            ]
        );
    }
}
