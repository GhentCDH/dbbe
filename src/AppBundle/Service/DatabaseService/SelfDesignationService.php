<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class SelfDesignationService extends DatabaseService
{
    /**
     * Get all self designation ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                self_designation.id as self_designation_id
            from data.self_designation'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getSelfDesignationsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                self_designation.id as self_designation_id,
                self_designation.name
            from data.self_designation
            where self_designation.id in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string $name
     * @return int
     */
    public function insert(string $name): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.self_designation (name)
                values (?)',
                [
                    $name
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    self_designation.id as self_designation_id
                from data.self_designation
                order by id desc
                limit 1'
            )->fetch()['self_designation_id'];
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
            'UPDATE data.self_designation
            set name = ?
            where self_designation.id = ?',
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
        // don't delete if this self designation is used in person
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.person_self_designation
            where person_self_designation.idself_designation = ?',
            [$id]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This self designation has dependencies.');
        }

        return $this->conn->executeUpdate(
            'DELETE from data.self_designation
            where self_designation.id = ?',
            [$id]
        );
    }
}
