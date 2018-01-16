<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\ORM\EntityManagerInterface;

use AppBundle\Model\FuzzyDate;
use AppBundle\Model\FuzzyInterval;

use Psr\Cache\CacheItemPoolInterface;

/**
 * The DatabaseService is the parent database service class.
 * It provides common functions that can be reused by its child classes.
 */
class DatabaseService
{
    /**
     * The connection to the database.
     * @var \Doctrine\DBAL\Connection
     */
    protected $conn;

    /**
     * The cache to store database query results.
     * @var \Symfony\Component\Cache\Adapter\ApcuAdapter
     */
    protected $cache;

    /**
     * Creates a new DatabaseService that operates on the given entity manager
     * @param EntityManagerInterface $entityManager
     * @param CacheItemPoolInterface $cacheItemPool
     */
    public function __construct(EntityManagerInterface $entityManager, CacheItemPoolInterface $cacheItemPool)
    {
        $this->conn = $entityManager->getConnection();
        $this->cache = $cacheItemPool;
    }

    /**
     * Get the content descriptions for content with ids $ids.
     * @param  array $ids The ids of the content.
     * @return array The contents with for each row as key the content id and as value
     *               an array with first the parent objects and in the end the last child object
     *               For each of these objects, the 'id'  and 'name' are returned
     */
    protected function getContentDescriptions(array $ids): array
    {
        $statement = $this->conn->executeQuery(
            'WITH RECURSIVE rec (idgenre, idparentgenre, genre, ids, names, depth) AS (
            	SELECT
            		g.idgenre,
            		g.idparentgenre,
            		g.genre,
                    g.idgenre::text as ids,
                    g.genre AS names,
            		1
            	FROM data.genre g


            	UNION ALL

            	SELECT
            		g.idgenre,
            		g.idparentgenre,
            		g.genre,
                    r.ids || \':\' || g.idgenre::text AS ids,
                    r.names || \':\' || g.genre AS names,
            		r.depth + 1

            	FROM rec AS r
            	INNER JOIN data.genre g
            	ON r.idgenre = g.idparentgenre
            )
            SELECT r.idgenre, ids, names
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
        $rawContents = $statement->fetchAll();
        $contents = [];
        foreach ($rawContents as $rawContent) {
            $ids = explode(':', $rawContent['ids']);
            $names = explode(':', $rawContent['names']);
            foreach ($ids as $index => $id) {
                $contents[$rawContent['idgenre']][] = [
                    'id' => (int) $ids[$index],
                    'name' => $names[$index],
                ];
            }
        }
        return $contents;
    }
    /**
     * Get the full person descriptions for persons with ids $ids.
     * @param  array $ids The ids of the persons.
     * @return array The persons with for each row as key the person id and as value
     *               a concatenation of names and birth and death information.
     */
    protected function getPersonFullDescriptions(array $ids): array
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

    protected function getPersonShortDescriptions(array $ids): array
    {
        $statement = $this->conn->executeQuery(
            'SELECT person.identity, first_name, last_name, unprocessed
                from data.person
                inner join data.name on name.idperson = person.identity
                where person.identity in (?)',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        $raw_persons = $statement->fetchAll();
        $persons = [];
        foreach ($raw_persons as $raw_person) {
            $name_array = [
                $raw_person['first_name'],
                $raw_person['last_name']
            ];
            $name_array = array_filter($name_array);
            if (!empty($name_array)) {
                $description = implode(' ', $name_array);
            } else {
                $description = $raw_person['unprocessed'];
            }
            $persons[$raw_person['identity']] = $description;
        }
        return $persons;
    }

    /**
     * Get the region descriptions for regions with ids $ids.
     * @param  array $ids The ids of the regions.
     * @return array The regions with for each row as key the region id  and as value
     *               an array with first the parent objects and in the end the last child object
     *               For each of these objects, the 'id'  and 'name' are returned
     */
    protected function getRegions(array $ids): array
    {
        $statement = $this->conn->executeQuery(
            'WITH RECURSIVE rec (identity, parent_idregion, name, ids, names, depth) AS (
                SELECT
                    r.identity,
                    r.parent_idregion,
                    r.name,
                    r.identity::text as ids,
                    r.name AS names,
                    1
                FROM data.region r

                UNION ALL

                SELECT
                    r.identity,
                    r.parent_idregion,
                    r.name,
                    rec.ids || \':\' || r.identity::text AS ids,
                    rec.names || \':\' || r.name AS names,
                    rec.depth + 1

                FROM rec
                INNER JOIN data.region r
                ON rec.identity = r.parent_idregion
            )
            SELECT rec.identity, ids, names
            FROM rec
            INNER JOIN (
                SELECT identity, MAX(depth) AS maxdepth
                FROM rec
                GROUP BY identity
            ) rj
            ON rec.identity = rj.identity AND rec.depth = rj.maxdepth
            WHERE rec.identity in (?)',
            [$ids],
            [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        $rawRegions = $statement->fetchAll();
        $regions = [];
        foreach ($rawRegions as $rawRegion) {
            $ids = explode(':', $rawRegion['ids']);
            $names = explode(':', $rawRegion['names']);
            foreach ($ids as $index => $id) {
                $regions[$rawRegion['identity']][] = [
                    'id' => (int) $ids[$index],
                    'name' => $names[$index],
                ];
            }
        }
        return $regions;
    }

    protected function getBibliographyDescriptions(array $ids): array
    {
        // Books
        $statement = $this->conn->executeQuery(
            'SELECT
                book.identity,
                bibrole.idperson,
                bibrole.rank,
                document_title.title,
                book.year
            from data.book
            left join data.bibrole on book.identity = bibrole.iddocument and bibrole.type = ?
            inner join data.document_title on book.identity = document_title.iddocument
            where book.identity in (?)
            order by book.identity, bibrole.rank',
            ['author', $ids],
            [\PDO::PARAM_STR, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        $rawBooks = $statement->fetchAll();

        // Articles
        $statement = $this->conn->executeQuery(
            'SELECT
                article.identity,
                bibrole.idperson,
                bibrole.rank,
                document_title.title,
                journal.year
            from data.article
            left join data.bibrole on article.identity = bibrole.iddocument and bibrole.type = ?
            inner join data.document_title on article.identity = document_title.iddocument
            inner join data.document_contains on article.identity = document_contains.idcontent
            inner join data.journal on journal.identity = document_contains.idcontainer
            where article.identity in (?)
            order by article.identity, bibrole.rank',
            ['author', $ids],
            [\PDO::PARAM_STR, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        $rawArticles = $statement->fetchAll();

        // Get all author names
        $uniquePersons = self::getUniqueIds(array_merge($rawBooks, $rawArticles), 'idperson');
        $personDescriptions = $this->getPersonShortDescriptions($uniquePersons);

        // Construct author names for books
        $authorNames = [];
        foreach ($rawBooks as $rawBook) {
            $authorNames[$rawBook['identity']][] = $personDescriptions[$rawBook['idperson']];
        }

        // Add books to result
        $bibliographies = [];
        foreach ($rawBooks as $rawBook) {
            if (!array_key_exists($rawBook['identity'], $bibliographies)) {
                $bibliographies[$rawBook['identity']] =
                    '(Book) '
                    . implode(', ', $authorNames[$rawBook['identity']])
                    . ' - ' . $rawBook['title'] . ' - ' . $rawBook['year'];
            }
        }

        // Construct author names for articles
        $authorNames = [];
        foreach ($rawArticles as $rawArticle) {
            $authorNames[$rawArticle['identity']][] = $personDescriptions[$rawArticle['idperson']];
        }

        // Add books to result
        $bibliographies = [];
        foreach ($rawArticles as $rawArticle) {
            if (!array_key_exists($rawArticle['identity'], $bibliographies)) {
                $bibliographies[$rawArticle['identity']] =
                    '(Article) '
                    . implode(', ', $authorNames[$rawArticle['identity']])
                    . ' - ' . $rawArticle['title'] . ' - ' . $rawArticle['year'];
            }
        }

        return $bibliographies;
    }

    /**
     * Get all unique ids from a certain key in an array with objects.
     * @param  array  $rows Array with objects with at least a key $key, containing an id for that object.
     * @param  string $key  The key for which all unique values need to be listed.
     * @return array        The array with all unique ids.
     */
    protected static function getUniqueIds(array $rows, string $key): array
    {
        $uniqueIds = [];
        foreach ($rows as $row) {
            if (!in_array($row[$key], $uniqueIds)) {
                $uniqueIds[] = $row[$key];
            }
        }
        return $uniqueIds;
    }
}
