<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class TypeService extends PoemService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem'
        )->fetchAll();
    }

    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                typpers.type_id
            from (
                select
                    reconstructed_poem.identity as type_id,
                    bibrole.idperson as person_id
                from data.reconstructed_poem
                inner join data.bibrole on reconstructed_poem.identity = bibrole.iddocument

                union

                select
                    reconstructed_poem.identity as type_id,
                    person.identity as person_id
                from data.reconstructed_poem
                inner join data.factoid on reconstructed_poem.identity = factoid.object_identity
                inner join data.person on factoid.subject_identity = person.identity
            ) as typpers
            where typpers.person_id = ?',
            [$personId]
        )->fetchAll();
    }

    public function getDepIdsByMeterId(int $meterId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.poem_meter on reconstructed_poem.identity = poem_meter.idpoem
            where poem_meter.idmeter = ?',
            [$meterId]
        )->fetchAll();
    }

    public function getDepIdsByGenreId(int $genreId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.document_genre on reconstructed_poem.identity = document_genre.iddocument
            where document_genre.idgenre = ?',
            [$genreId]
        )->fetchAll();
    }

    public function getDepIdsByKeywordId(int $keywordId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                typkey.type_id
            from (
                select
                    reconstructed_poem.identity as type_id,
                    document_keyword.idkeyword as keyword_id
                from data.reconstructed_poem
                inner join data.document_keyword on reconstructed_poem.identity = document_keyword.iddocument

                union

                select
                    reconstructed_poem.identity as type_id,
                    keyword.identity as keyword_id
                from data.reconstructed_poem
                inner join data.factoid on reconstructed_poem.identity = factoid.object_identity
                inner join data.keyword on factoid.subject_identity = keyword.identity
            ) as typkey
            where typkey.keyword_id = ?',
            [$keywordId]
        )->fetchAll();
    }

    public function getDepIdsByAcknowledgementId(int $acknowledgementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.document_acknowledgement on reconstructed_poem.identity = document_acknowledgement.iddocument
            where document_acknowledgement.idacknowledgement = ?',
            [$acknowledgementId]
        )->fetchAll();
    }

    public function getVerses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document.identity as type_id,
                document.text_content
            from data.document
            where document.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getPrevIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                type_to_reconstructed_poem.identity as document_id,
                type_to_reconstructed_poem.idtype as prev_id
            from migration.type_to_reconstructed_poem
            where type_to_reconstructed_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getKeywords(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_keyword.iddocument as type_id,
                document_keyword.idkeyword as keyword_id
            from data.document_keyword
            where document_keyword.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getStatuses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_status.iddocument as type_id,
                status.idstatus as status_id,
                status.status as status_name,
                status.type as status_type
            from data.document_status
            inner join data.status on document_status.idstatus = status.idstatus
            where document_status.iddocument in (?)
            and status.type in (
                \'type_text\'
            )',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOccurrences(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id,
                factoid.subject_identity as occurrence_id
            from data.reconstructed_poem
            inner join data.factoid on reconstructed_poem.identity = factoid.object_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where reconstructed_poem.identity in (?)
            and factoid_type.type = \'reconstruction of\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getRelatedTypes(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id,
                factoid.subject_identity as rel_type_id
            from data.reconstructed_poem
            inner join data.factoid on reconstructed_poem.identity = factoid.object_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where reconstructed_poem.identity in (?)
            and factoid_type.type = \'related to\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
