<?php

namespace AppBundle\Service\DatabaseService;

class PersonService extends DatabaseService
{
    public function getPersonsByIds(array $ids): array
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
                vgk.identifier as vgk,
                pbw.identifier as pbw,
                occupation_merge.occupations
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
            ) as vgk on person.identity = vgk.idsubject
            left join (
                select
                    global_id.idsubject,
                    global_id.identifier
                from data.global_id
                inner join data.institution on global_id.idauthority = institution.identity
                where institution.name = \'Prosopography of the Byzantine World\'
            ) as pbw on person.identity = pbw.idsubject
            left join (
                select
                    person_occupation.idperson,
                    array_to_json(array_agg(occupation.occupation)) as occupations
                from data.person_occupation
                inner join data.occupation on person_occupation.idoccupation = occupation.idoccupation
                where occupation.is_function = TRUE
                group by person_occupation.idperson
            ) as occupation_merge on person.identity = occupation_merge.idperson
            where person.identity in (?)',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
