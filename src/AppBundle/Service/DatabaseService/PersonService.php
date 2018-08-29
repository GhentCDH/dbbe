<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class PersonService extends EntityService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                person.identity as person_id
            from data.person'
        )->fetchAll();
    }

    public function getHistoricalIds(): array
    {
        return $this->conn->query(
            'SELECT
                person.identity as person_id
            from data.person
            where person.is_historical = TRUE'
        )->fetchAll();
    }

    public function getModernIds(): array
    {
        return $this->conn->query(
            'SELECT
                person.identity as person_id
            from data.person
            where person.is_modern = TRUE'
        )->fetchAll();
    }

    public function getDepIdsByOfficeId(int $officeId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_occupation.idperson as person_id
            from data.person_occupation
            where person_occupation.idoccupation = ?',
            [$officeId]
        )->fetchAll();
    }

    public function getDepIdsByOfficeIdWithChildren(int $officeId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_occupation.idperson as person_id
            from data.person_occupation
            where person_occupation.idoccupation in (
                WITH RECURSIVE rec (id, idparent) AS (
                    SELECT
                        o.idoccupation,
                        o.idparentoccupation
                    FROM data.occupation as o

                    UNION ALL

                    SELECT
                        rec.id,
                        o.idparentoccupation
                    FROM rec
                    INNER JOIN data.occupation o
                    ON o.idoccupation = rec.idparent
                )
                SELECT id
                FROM rec
                WHERE rec.idparent = ? or rec.id = ?
            )',
            [
                $officeId,
                $officeId,
            ]
        )->fetchAll();
    }

    public function getBasicInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id,
                name.first_name,
                name.last_name,
                name.extra,
                name.unprocessed,
                person.is_historical,
                person.is_modern,
                factoid_born.born_date,
                factoid_died.death_date
            from data.person
            inner join data.name on name.idperson = person.identity
            left join (
                select
                    factoid.subject_identity,
                    factoid.date as born_date
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'born\'
            ) as factoid_born on person.identity = factoid_born.subject_identity
            left join (
                select
                    factoid.subject_identity,
                    factoid.date as death_date
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'died\'
            ) as factoid_died on person.identity = factoid_died.subject_identity
            where person.identity in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as document_id,
                bibrole.idrole as role_id,
                case
                    when manuscript.identity is not null then \'manuscript\'
                    when original_poem.identity is not null then \'occurrence\'
                    when reconstructed_poem.identity is not null then \'type\'
                end as document_key
            from data.bibrole
            left join data.manuscript on bibrole.iddocument = manuscript.identity
            left join data.original_poem on bibrole.iddocument = original_poem.identity
            left join data.reconstructed_poem on bibrole.iddocument = reconstructed_poem.identity
            where bibrole.idperson in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }


    public function getOffices(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_occupation.idperson as person_id,
                person_occupation.idoccupation as office_id
            from data.person_occupation
            where person_occupation.idperson in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getManuscripts(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT -- bibrole of manuscript
                bibrole.idperson as person_id,
                manuscript.identity as manuscript_id,
                null as occurrence_id,
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name
            from data.bibrole
            inner join data.manuscript on bibrole.iddocument = manuscript.identity
            inner join data.role on bibrole.idrole = role.idrole
            where bibrole.idperson in (?)

            union all

            SELECT -- bibrole of occurrence in manuscript
                bibrole.idperson as person_id,
                manuscript.identity as manuscript_id,
                document_contains.idcontent as occurrence_id,
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name
            from data.bibrole
            inner join data.document_contains on bibrole.iddocument = document_contains.idcontent
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            inner join data.role on bibrole.idrole = role.idrole
            where bibrole.idperson in (?)',
            [
                $ids,
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function insert(): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_manuscript_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.person default values'
            );
            $personId = $this->conn->executeQuery(
                'SELECT
                    person.identity as person_id
                from data.person
                order by identity desc
                limit 1'
            )->fetch()['person_id'];
            $this->conn->executeUpdate(
                'INSERT INTO data.name (idperson) values (?)',
                [
                    $personId,
                ]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $personId;
    }

    public function updateFirstName(int $personId, string $firstName = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.name
            set first_name = ?
            where name.idperson = ?',
            [
                $firstName,
                $personId,
            ]
        );
    }

    public function updateLastName(int $personId, string $lastName = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.name
            set last_name = ?
            where name.idperson = ?',
            [
                $lastName,
                $personId,
            ]
        );
    }

    public function updateExtra(int $personId, string $extra = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.name
            set extra = ?
            where name.idperson = ?',
            [
                $extra,
                $personId,
            ]
        );
    }

    public function updateHistorical(int $personId, bool $historical): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.person
            set is_historical = ?
            where person.identity = ?',
            [
                $historical ? 'TRUE': 'FALSE',
                $personId,
            ]
        );
    }

    public function updateModern(int $personId, bool $modern): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.person
            set is_modern = ?
            where person.identity = ?',
            [
                $modern ? 'TRUE': 'FALSE',
                $personId,
            ]
        );
    }

    public function delOffices(int $personId, array $officeIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.person_occupation
            where person_occupation.idperson = ?
            and person_occupation.idoccupation in (?)',
            [
                $personId,
                $officeIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function addOffice(int $personId, int $officeId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.person_occupation (idperson, idoccupation)
            values (?, ?)',
            [
                $personId,
                $officeId,
            ]
        );
    }

    public function delete(int $personId): int
    {
        $this->beginTransaction();
        try {
            // don't delete if this person is used in bibrole
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.bibrole
                where bibrole.idperson = ?',
                [$personId]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This person has bibrole dependencies.');
            }
            // don't delete if this person is used in factoid
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.factoid
                where factoid.object_identity = ?',
                [$personId]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This person has factoid dependencies.');
            }
            // Set search_path for triggers
            $this->conn->exec('SET SEARCH_PATH TO data');
            // $this->conn->executeUpdate(
            //     'DELETE from data.factoid
            //     where factoid.subject_identity = ?',
            //     [$personId]
            // );
            $delete = $this->conn->executeUpdate(
                'DELETE from data.person
                where person.identity = ?',
                [$personId]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $delete;
    }
}
