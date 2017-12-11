<?php

namespace AppBundle\Service;

use Twig_Tests_LegacyIntegrationTest;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Id;

class DatabaseService
{
    protected $conn;

    public function __construct(EntityManager $entityManager)
    {
        $this->conn = $entityManager->getConnection();
    }

    /**
     * Returns all manuscripts in the database.
     * The return data contains these fields:
     * - id
     * - name
     * - date_floor: earliest estimate for the creation date of a manuscript.
     * - date_ceiling: latest estimate for the creation date of a manuscript.
     * - genre: string containing all genre information, in following format:
     *   parent_genre1: child_genre1|parent_genre2: child_genre2
     * @return array All manuscripts found in the database.
     */
    public function getAllManuscripts(): array
    {
        $statement = $this->conn->prepare(
            'SELECT document.identity,
                document_title.title AS name,
                factoid_merge.factoid_date AS completion_date
            FROM data.manuscript
            JOIN data.document ON manuscript.identity = document.identity
            JOIN data.document_title ON document.identity = document_title.iddocument
            LEFT JOIN (
                SELECT factoid.subject_identity AS factoid_identity,
                    factoid.date AS factoid_date
                FROM data.factoid
                INNER JOIN data.factoid_type
                    ON factoid.idfactoid_type = factoid_type.idfactoid_type
                        AND factoid_type.type = \'completed at\'
            ) factoid_merge ON manuscript.identity = factoid_merge.factoid_identity'
        );
        $statement->execute();
        $raw_manuscripts = $statement->fetchAll();

        $manuscripts = [];

        // Transform to requested format
        foreach ($raw_manuscripts as $raw_ms) {
            // Extract date_floor and date_ceiling
            preg_match(
                '/[(](\d{4}[-]\d{2}-\d{2})[,](\d{4}[-]\d{2}-\d{2})[)]/',
                $raw_ms['completion_date'],
                $fuzzy_date
            );
            if (count($fuzzy_date) == 0) {
                $fuzzy_date = [null, null, null];
            }

            $manuscripts[] = [
                'id' => $raw_ms['identity'],
                'name' => $raw_ms['name'],
                'date_floor' => $fuzzy_date[1],
                'date_ceiling' => $fuzzy_date[2],
                'genre' => $this->getDocumentGenres($raw_ms['identity']),
            ];
        }

        return $manuscripts;
    }

    /**
     * Get the genres that are linked to the document with id $documentId.
     * Format: parent_genre1: child_genre1|parent_genre2: child_genre2|...
     * @param  int    $documentId The id of the document.
     * @return string             The genres, formatted as indicated above.
     */
    private function getDocumentGenres(int $documentId): string
    {
        $statement = $this->conn->prepare(
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
            SELECT concat
            FROM data.document_genre
            INNER JOIN (
            	SELECT r.*
            	FROM rec r
            	INNER JOIN (
            		SELECT idgenre, MAX(depth) AS maxdepth
            		FROM rec
            		GROUP BY idgenre
            	) rj
            	ON r.idgenre = rj.idgenre AND r.depth = rj.maxdepth
            ) rec_max
            ON document_genre.idgenre = rec_max.idgenre
            WHERE iddocument = :iddocument'
        );
        $statement->bindValue('iddocument', $documentId);
        $statement->execute();
        $genres = $statement->fetchAll();

        $concats = [];
        foreach ($genres as $genre) {
            $concats[] = $genre['concat'];
        }
        return implode('|', $concats);
    }
}
