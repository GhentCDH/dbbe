<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class PoemService extends DocumentService
{
    public function getIncipits(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                poem.identity as poem_id,
                poem.incipit
            from data.poem
            where poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getTitles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_title.iddocument as poem_id,
                document_title.title
            from data.document_title
            where document_title.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getMeters(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                poem.identity as poem_id,
                meter.idmeter as meter_id,
                meter.name as name
            from data.poem
            inner join data.poem_meter on poem.identity = poem_meter.idpoem
            inner join data.meter on poem_meter.idmeter = meter.idmeter
            where poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getSubjects(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                poem.identity as poem_id,
                person.identity as person_id,
                keyword.identity as keyword_id
            from data.poem
            inner join data.factoid on poem.identity = factoid.object_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            left join data.person on factoid.subject_identity = person.identity
            left join data.keyword on factoid.subject_identity = keyword.identity
            where poem.identity in (?)
            and factoid_type.type = \'subject of\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getGenres(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                poem.identity as poem_id,
                genre.idgenre as genre_id,
                genre.genre as name
            from data.poem
            inner join data.document_genre on poem.identity = document_genre.iddocument
            inner join data.genre on document_genre.idgenre = genre.idgenre
            where poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAcknowledgements(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_acknowledgement.iddocument as poem_id,
                document_acknowledgement.idacknowledgement as acknowledgement_id,
                acknowledgement.acknowledgement as name
            from data.document_acknowledgement
            inner join data.acknowledgement on document_acknowledgement.idacknowledgement = acknowledgement.id
            where document_acknowledgement.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
