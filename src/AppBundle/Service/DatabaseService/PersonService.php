<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

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

    public function getBasicInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id,
                name.first_name,
                name.last_name,
                name.extra,
                name.unprocessed,
                factoid_born.born_date,
                factoid_died.death_date,
                rgki.identifier as rgki,
                rgkii.identifier as rgkii,
                rgkiii.identifier as rgkiii,
                vgh.identifier as vgh,
                pbw.identifier as pbw,
                person.is_historical
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
            left join (
                select
                    global_id.idsubject,
                    global_id.identifier
                from data.global_id
                inner join data.document_title on global_id.idauthority = document_title.iddocument
                where document_title.title = \'Repertorium der griechischen Kopisten 800-1600. 1. Grossbritannien A. Verzeichnis der Kopisten\'
            ) as rgki on person.identity = rgki.idsubject
            left join (
                select
                    global_id.idsubject,
                    global_id.identifier
                from data.global_id
                inner join data.document_title on global_id.idauthority = document_title.iddocument
                where document_title.title = \'Repertorium der griechischen Kopisten 800-1600. 2. Frankreich A. Verzeichnis der Kopisten\'
            ) as rgkii on person.identity = rgkii.idsubject
            left join (
                select
                    global_id.idsubject,
                    global_id.identifier
                from data.global_id
                inner join data.document_title on global_id.idauthority = document_title.iddocument
                where document_title.title = \'Repertorium der griechischen Kopisten 800-1600. 3. Rom mit dem Vatikan A. Verzeichnis der Kopisten\'
            ) as rgkiii on person.identity = rgkiii.idsubject
            left join (
                select
                    global_id.idsubject,
                    global_id.identifier
                from data.global_id
                inner join data.document_title on global_id.idauthority = document_title.iddocument
                where document_title.title = \'Die griechischen Schreiber des Mittelalters und der Renaissance\'
            ) as vgh on person.identity = vgh.idsubject
            left join (
                select
                    global_id.idsubject,
                    global_id.identifier
                from data.global_id
                inner join data.institution on global_id.idauthority = institution.identity
                where institution.name = \'Prosopography of the Byzantine World\'
            ) as pbw on person.identity = pbw.idsubject
            where person.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOccupations(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_occupation.idperson as person_id,
                person_occupation.idoccupation as occupation_id
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
                bibrole.type
            from data.bibrole
            inner join data.manuscript on bibrole.iddocument = manuscript.identity
            where bibrole.idperson in (?)
            and bibrole.type in (\'scribe\', \'patron\')

            union

            SELECT -- bibrole of occurrence in manuscript
                bibrole.idperson as person_id,
                manuscript.identity as manuscript_id,
                document_contains.idcontent as occurrence_id,
                bibrole.type
            from data.bibrole
            inner join data.document_contains on bibrole.iddocument = document_contains.idcontent
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            where bibrole.idperson in (?)
            and bibrole.type in (\'scribe\', \'patron\')

            union

            SELECT -- related person to manuscript
                factoid.object_identity as person_id,
                factoid.subject_identity as manuscript_id,
                null as occurrence_id,
                \'related\' as type
            from data.factoid
            inner join data.person on factoid.object_identity = person.identity
            inner join data.manuscript on factoid.subject_identity = manuscript.identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where person.identity in (?)
            and type = \'related to\'',
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

    public function getIdsByOccupations(array $occupations): array
    {
        return $this->conn->executeQuery(
            'SELECT
                occupation.occupation,
                person.identity as person_id
            from data.person
            inner join data.person_occupation on person.identity = person_occupation.idperson
            inner join data.occupation on person_occupation.idoccupation = occupation.idoccupation
            where occupation.occupation in (?)',
            [$occupations],
            [Connection::PARAM_STR_ARRAY]
        )->fetchAll();
    }
}
