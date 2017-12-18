<?php

namespace AppBundle\Service;

use Doctrine\ORM\EntityManager;

use AppBundle\Model\FuzzyDate;

class DatabaseService
{
    protected $conn;

    public function __construct(EntityManager $entityManager)
    {
        $this->conn = $entityManager->getConnection();
    }

    /**
     * Returns all manuscripts and related contents in the database.
     * The return data contains the arrays manuscripts and manuscript_contents with these fields:
     * - manuscripts
     *   - id
     *   - name
     *   - name_suggest: combinations of name parts for autocomplete
     *   - date_floor: earliest estimate for the creation date of a manuscript.
     *   - date_ceiling: latest estimate for the creation date of a manuscript.
     *   - content: string containing all content information, in following format:
     *     parent_content1: child_content1|parent_content2: child_content2
     * - manuscript_contents
     *   - id
     *   - name: string containing all content information, in following format:
     *     parent_content: child_content
     *   - name_suggest: combinations of name parts for autocomplete
     * @return array All manuscripts and related contents found in the database.
     */
    public function getAllManuscriptsAndContents(): array
    {
        // Get all manuscripts from the database
        $rawManuscripts = $this->getAllManuscripts();

        // Get all manuscript content relations from the database
        $mcRelations = $this->getAllManuscriptContentRelations();

        // Filter out all unique content identifiers
        $mcUniqueIds = [];
        foreach ($mcRelations as $mcRelation) {
            if (!in_array($mcRelation['idgenre'], $mcUniqueIds)) {
                $mcUniqueIds[] = $mcRelation['idgenre'];
            }
        }

        // Get all content info from the list of unique identifiers
        $rawContents = $this->getContents($mcUniqueIds);

        // Create an array of content names per manuscript id
        $indexedContents = [];
        foreach ($mcRelations as $mcRelation) {
            if (!array_key_exists($mcRelation['iddocument'], $indexedContents)) {
                $indexedContents[$mcRelation['iddocument']] = [];
            }
            foreach ($rawContents as $mc) {
                if ($mc['id'] == $mcRelation['idgenre']) {
                    $indexedContents[$mcRelation['iddocument']][] = $mc['name'];
                    break;
                }
            }
        }

        return [
            'manuscripts' => $this->formatManuscripts($rawManuscripts, $indexedContents),
            'contents' => $this->formatContents($rawContents)
        ];
    }

    /**
     * Get all manuscripts from the database
     * @return array All manuscripts in the database. Provided information:
     * - identity
     * - name
     * - completion_date
     */
    private function getAllManuscripts(): array
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
        return $statement->fetchAll();
    }

    /**
     * Format an array of manuscripts for indexing in elasticsearch.
     * @param  array $rawManuscripts  The raw information of all manuscripts.
     * @param  array $indexedContents An array with per manuscript id (key) an array of content names.
     * @return array                  An array containing manuscript and related content information,
     *                                ready for indexing in elasticsearch.
     */
    private function formatManuscripts(array $rawManuscripts, array $indexedContents): array
    {
        $manuscripts = [];

        foreach ($rawManuscripts as $rawManuscript) {
            $fuzzyDate = new FuzzyDate($rawManuscript['completion_date']);

            $manuscripts[] = [
                'id' => $rawManuscript['identity'],
                'name' => $rawManuscript['name'],
                'name_suggest' => [
                    'input' => $this->findCleanPermutations(explode(' ', $rawManuscript['name'])),
                ],
                'date_floor' => $fuzzyDate->getFloor(),
                'date_ceiling' => $fuzzyDate->getCeiling(),
                'content' => array_key_exists($rawManuscript['identity'], $indexedContents)
                    ? implode('|', $indexedContents[$rawManuscript['identity']]) : '',
            ];
        }

        return $manuscripts;
    }

    /**
     * Get all contents linked to a manuscript from the database.
     * @return array All contents linked to a manuscript. Fields per entry:
     * - iddocument
     * - idgenre
     */
    private function getAllManuscriptContentRelations(): array
    {
        $statement = $this->conn->prepare(
            'SELECT iddocument, idgenre
            FROM data.manuscript
            JOIN data.document_genre
            ON manuscript.identity = document_genre.iddocument'
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * Get the contents with ids $ids.
     * @param  array $ids The ids of the genres.
     * @return array The contents. Fields per entry:
     * id: id of the content
     * name: name of the content item and parent items as follows:
     *       'grandparent: parent: child'.
     */
    private function getContents(array $ids): array
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
            		r.concat || \': \' || g.genre AS concat,
            		r.depth + 1

            	FROM rec AS r
            	INNER JOIN data.genre g
            	ON r.idgenre = g.idparentgenre
            )
            SELECT r.idgenre as id, concat as name
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
        return $statement->fetchAll();
    }

    /**
     * Format an array of contents for indexing in elasticsearch
     * @param  array $rawContents The raw information of the contents to be formatted.
     * @return array              Content information, ready for indexing in elasticsearch.
     */
    private function formatContents(array $rawContents): array
    {
        $contents = [];
        foreach ($rawContents as $rawContent) {
            $contents[] = [
                'id' => $rawContent['id'],
                'name' => $rawContent['name'],
                'name_suggest' => [
                    'input' => $this->findCleanPermutations(preg_split('/[:]|[ ]/', $rawContent['name'])),
                ],
            ];
        }
        return $contents;
    }

    /**
     * Given an array of inputs:
     * - remove inputs that should not be available for autocompletion
     *   e.g., strings with a length of one or less
     * - find all combinations of the input strings, taking the order of the inputs into account.
     * @param  array $inputs A list with inputs that needs an improved list for autocompletion.
     * @return array         A list that is more autocompletion friendly.
     */
    private function findCleanPermutations(array $inputs): array
    {
        // Remove strings with a length smaller or equal to one
        $cleanInputs = [];
        foreach ($inputs as $input) {
            if (strlen($input) > 1) {
                $cleanInputs[] = $input;
            }
        }

        // Make permutations to make suggestions with multiple word input possible
        $count = count($cleanInputs);
        $members = pow(2, $count);
        $permutations = [];
        for ($i = 0; $i < $members; $i++) {
            $b = sprintf("%0" . $count . "b", $i);
            $out = [];
            for ($j = 0; $j < $count; $j++) {
                if ($b{$j} == '1') {
                    $out[] = $cleanInputs[$j];
                }
            }

            if (count($out) >= 1) {
                $permutations[] = implode(' ', $out);
            }
        }
        return $permutations;
    }
}
