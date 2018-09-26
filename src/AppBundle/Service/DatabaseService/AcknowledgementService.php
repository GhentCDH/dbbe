<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class AcknowledgementService extends DatabaseService
{
    /**
     * Get all acknowledgement ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                acknowledgement.id as acknowledgement_id
            from data.acknowledgement'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getAcknowledgementsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                acknowledgement.id as acknowledgement_id,
                acknowledgement.acknowledgement as name
            from data.acknowledgement
            where acknowledgement.id in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string   $name
     * @return int
     */
    public function insert(string $name): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.acknowledgement (acknowledgement)
                values (?)',
                [
                    $name
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    acknowledgement.id as acknowledgement_id
                from data.acknowledgement
                order by id desc
                limit 1'
            )->fetch()['acknowledgement_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * @param  int    $id
     * @param  string $name
     * @return int
     */
    public function updateName(int $id, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.acknowledgement
            set acknowledgement = ?
            where acknowledgement.id = ?',
            [
                $name,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        // don't delete if this acknowledgement is used in poem_acknowledgement
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.poem_acknowledgement
            where poem_acknowledgement.id = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This acknowledgement has dependencies.');
        }

        return $this->conn->executeUpdate(
            'DELETE from data.acknowledgement
            where acknowledgement.id = ?',
            [$id]
        );
    }
}
