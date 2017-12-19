<?php

namespace AppBundle\Service\DatabaseService;

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

    /**
     * Get all unique content ids from an array with as keys document ids and as values content ids.
     * @param  array $documentContentIds An array with as keys document ids and as values content ids.
     * @return array                     An array with the unique content ids.
     */
    protected function getUniqueDocumentContentIds(array $documentContentIds): array
    {
        $uniqueIds = [];
        foreach ($documentContentIds as $contentIds) {
            foreach ($contentIds as $contentId) {
                if (!in_array($contentId, $uniqueIds)) {
                    $uniqueIds[] = $contentId;
                }
            }
        }
        return $uniqueIds;
    }
}
