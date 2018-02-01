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
                factoid_died.death_date
            from data.person
            inner join data.name on name.idperson = person.identity
            left join (
                select subject_identity, date as born_date
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'born\'
            ) as factoid_born on person.identity = factoid_born.subject_identity
            left join (
                select subject_identity, date as death_date
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'died\'
            ) as factoid_died on person.identity = factoid_died.subject_identity
            where person.identity in (?)',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
