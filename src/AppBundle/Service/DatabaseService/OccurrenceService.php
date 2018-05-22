<?php

namespace AppBundle\Service\DatabaseService;

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

    public function getLocations(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                document_contains.folium_start,
                document_contains.folium_start_recto,
                document_contains.folium_end,
                document_contains.folium_end_recto,
                document_contains.general_location,
                document_contains.idcontainer as manuscript_id
            from data.original_poem
            inner join data.document_contains on original_poem.identity = document_contains.idcontent
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getIncipit(array $ids): array
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

    public function getTexts(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                document.text_content
            from data.original_poem
            inner join data.document on original_poem.identity = document.identity
            where original_poem.identity in (?)',
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

    public function getBibroles(array $ids, array $roles): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                bibrole.idperson as person_id,
                bibrole.type
            from data.original_poem
            inner join data.bibrole on original_poem.identity = bibrole.iddocument
            where original_poem.identity in (?)
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

    public function getPublics(array $ids = null): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                entity.public
            from data.original_poem
            inner join data.entity on original_poem.identity = entity.identity
            where entity.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
