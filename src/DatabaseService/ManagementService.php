<?php

namespace App\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class ManagementService extends DatabaseService
{
    public function getManagementsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                management.id as management_id,
                management.name as management_name
            from data.management
            where management.id in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAllManagements(): array
    {
        return $this->conn->query(
            'SELECT
            management.id as management_id,
            management.name as management_name
            from data.management'
        )->fetchAll();
    }

    public function getManagementsByType(string $type): array
    {
        return $this->conn->executeQuery(
            'SELECT
                management.id as management_id,
                management.name as management_name
            from data.management
            where ? = ANY(management.type)',
            [$type]
        )->fetchAll();
    }

    public function insert(string $name): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.management (name)
                values (?)',
                [
                    $name,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    management.id as management_id
                from data.management
                order by id desc
                limit 1'
            )->fetch()['management_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    public function updateName(int $id, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.management
            set name = ?
            where management.id = ?',
            [
                $name,
                $id,
            ]
        );
    }

    public function delete(int $id): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.management
            where management.id = ?',
            [
                $id,
            ]
        );
    }
}
