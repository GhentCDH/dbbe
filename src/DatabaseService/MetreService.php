<?php

namespace App\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use App\Exceptions\DependencyException;

class MetreService extends DatabaseService
{
    /**
     * Get all metre ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                meter.idmeter as metre_id
            from data.meter'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getMetresByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                meter.idmeter as metre_id,
                meter.name
            from data.meter
            where meter.idmeter in (?)',
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
                'INSERT INTO data.meter (name)
                values (?)',
                [
                    $name
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    meter.idmeter as metre_id
                from data.meter
                order by idmeter desc
                limit 1'
            )->fetch()['metre_id'];
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
            'UPDATE data.meter
            set name = ?
            where meter.idmeter = ?',
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
        // don't delete if this metre is used in poem_meter
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.poem_meter
            where poem_meter.idmeter = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This metre has dependencies.');
        }

        return $this->conn->executeUpdate(
            'DELETE from data.meter
            where meter.idmeter = ?',
            [$id]
        );
    }
}
