<?php

namespace AppBundle\Service\DatabaseService;

use AppBundle\Model\FuzzyInterval;
use Doctrine\ORM\EntityManagerInterface;

use AppBundle\Model\FuzzyDate;

class DatabaseService
{
    protected $conn;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->conn = $entityManager->getConnection();
    }

    /**
     * Get the contents with ids $ids.
     * @param  array $ids The ids of the genres.
     * @return array The contents with
     * as key the contentid
     * as value an array with the names of the content item and its parents: grandparent, parent, child.
     */
    protected function getContents(array $ids): array
    {
        $statement = $this->conn->executeQuery(
            'WITH RECURSIVE rec (idgenre, idparentgenre, genre, concat, depth) AS (
            	SELECT
            		g.idgenre,
            		g.idparentgenre,
            		g.genre,
            		g.genre AS concat,
            		1
            	FROM data.genre g


            	UNION ALL

            	SELECT
            		g.idgenre,
            		g.idparentgenre,
            		g.genre,
            		r.concat || \':\' || g.genre AS concat,
            		r.depth + 1

            	FROM rec AS r
            	INNER JOIN data.genre g
            	ON r.idgenre = g.idparentgenre
            )
            SELECT r.idgenre, concat
	        FROM rec r
            INNER JOIN (
            	SELECT idgenre, MAX(depth) AS maxdepth
            	FROM rec
            	GROUP BY idgenre
            ) rj
            ON r.idgenre = rj.idgenre AND r.depth = rj.maxdepth
            WHERE r.idgenre in (?)',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        $contents = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($contents as $contentid => $content) {
            $contents[$contentid] = explode(':', $content);
        }
        return $contents;
    }

    protected function getPersonDescriptions(array $ids): array
    {
        $statement = $this->conn->executeQuery(
            'SELECT person.identity, first_name, last_name, extra, unprocessed, born_date, death_date
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
        );
        $raw_persons = $statement->fetchAll();
        $persons = [];
        foreach ($raw_persons as $raw_person) {
            $name_array = [
                $raw_person['first_name'],
                $raw_person['last_name'],
                $raw_person['extra'],
            ];
            $name_array = array_filter($name_array);
            if (!empty($name_array)) {
                $description = implode(' ', $name_array);
                if (!empty($raw_person['born_date']) && !empty($raw_person['death_date'])) {
                    $description .= ' (' . new FuzzyInterval(
                        new FuzzyDate($raw_person['born_date']),
                        new FuzzyDate($raw_person['death_date'])
                    ) . ')';
                }
            } else {
                $description = $raw_person['unprocessed'];
            }
            $persons[$raw_person['identity']] = $description;
        }
        return $persons;
    }

    /**
     * Get all unique other entity ids from an array with as keys entity ids and as other entity ids.
     * @param  array $ids An array with as keys entity ids and as values other entity ids.
     * @return array      An array with the unique other entity ids.
     */
    protected function getUniqueIds(array $ids): array
    {
        $uniqueIds = [];
        foreach ($ids as $entryIds) {
            foreach ($entryIds as $entryId) {
                if (!in_array($entryId, $uniqueIds)) {
                    $uniqueIds[] = $entryId;
                }
            }
        }
        return $uniqueIds;
    }
}
