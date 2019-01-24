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

    public function getNumberOfVerses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                poem.identity as poem_id,
                poem.verses
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

    public function getMetres(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                poem.identity as poem_id,
                meter.idmeter as metre_id,
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
     * @param  string $langCode
     * @param  string $title
     * @return int
     */
    public function upsertTitle(int $id, string $langCode, string $title): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.document_title (iddocument, idlanguage, title)
            values (
                ?,
                (
                    select idlanguage
                    from data.language
                    where language.code = ?
                ),
                ?
            )
            -- primary key constraint on iddocument, idlanguage
            on conflict (iddocument, idlanguage) do update
            set title = excluded.title',
            [
                $id,
                $langCode,
                $title,
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
     * @param  int $metreId
     * @return int
     */
    public function addMetre(int $id, int $metreId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.poem_meter (idpoem, idmeter)
            values (?, ?)',
            [
                $id,
                $metreId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $metreIds
     * @return int
     */
    public function delMetres(int $id, array $metreIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.poem_meter
            where idpoem = ?
            and idmeter in (?)',
            [
                $id,
                $metreIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $genreId
     * @return int
     */
    public function addGenre(int $id, int $genreId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.document_genre (iddocument, idgenre)
            values (?, ?)',
            [
                $id,
                $genreId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $genreIds
     * @return int
     */
    public function delGenres(int $id, array $genreIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.document_genre
            where iddocument = ?
            and idgenre in (?)',
            [
                $id,
                $genreIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $subjectId
     * @return int
     */
    public function addSubject(int $id, int $subjectId): int
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
                    where factoid_type.type = \'subject of\'
                )
            )',
            [
                $subjectId,
                $id,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $subjectIds
     * @return int
     */
    public function delSubjects(int $id, array $subjectIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.factoid
            where subject_identity in (?)
            and object_identity = ?',
            [
                $subjectIds,
                $id,
            ],
            [
                Connection::PARAM_INT_ARRAY,
                \PDO::PARAM_INT,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $acknowledgementId
     * @return int
     */
    public function addAcknowledgement(int $id, int $acknowledgementId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.document_acknowledgement (iddocument, idacknowledgement)
            values (?, ?)',
            [
                $id,
                $acknowledgementId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $acknowledgementIds
     * @return int
     */
    public function delAcknowledgements(int $id, array $acknowledgementIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.document_genre
            where iddocument = ?
            and idacknowledgement in (?)',
            [
                $id,
                $acknowledgementIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }
}
