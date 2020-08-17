<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class RoleService extends DatabaseService
{
    public function getRolesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name,
                role.is_contributor_role as role_is_contributor_role,
                role.has_rank as role_has_rank,
                role.order as role_order
            from data.role
            where role.idrole in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAllRoles(): array
    {
        return $this->conn->query(
            'SELECT
            role.idrole as role_id,
            array_to_json(role.type) as role_usage,
            role.system_name as role_system_name,
            role.name as role_name,
            role.is_contributor_role as role_is_contributor_role,
            role.has_rank as role_has_rank,
            role.order as role_order
            from data.role'
        )->fetchAll();
    }

    public function getByType(string $type): array
    {
        return $this->conn->executeQuery(
            'SELECT
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name,
                role.is_contributor_role as role_is_contributor_role,
                role.has_rank as role_has_rank,
                role.order as role_order
            from data.role
            where (is_contributor_role is null or is_contributor_role = false)
            and ? = ANY(role.type)',
            [$type]
        )->fetchAll();
    }

    public function getContributorByType(string $type): array
    {
        return $this->conn->executeQuery(
            'SELECT
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name,
                role.is_contributor_role as role_is_contributor_role,
                role.has_rank as role_has_rank,
                role.order as role_order
            from data.role
            where is_contributor_role = true
            and ? = ANY(role.type)',
            [$type]
        )->fetchAll();
    }

    public function insert(array $usage, string $systemName, string $name, bool $contributorRole, bool $rank): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.role (type, system_name, name, is_contributor_role, has_rank)
                values (?, ?, ?, ?, ?)',
                [
                    '{' . implode(',', $usage) . '}',
                    $systemName,
                    $name,
                    $contributorRole ? 'TRUE': 'FALSE',
                    $rank ? 'TRUE': 'FALSE',
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    role.idrole as role_id
                from data.role
                order by idrole desc
                limit 1'
            )->fetch()['role_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    public function updateUsage(int $roleId, array $usage): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.role
            set type = ?, modified = now()
            where role.idrole = ?',
            [
                '{' . implode(',', $usage) . '}',
                $roleId,
            ]
        );
    }

    public function updateName(int $roleId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.role
            set name = ?, modified = now()
            where role.idrole = ?',
            [
                $name,
                $roleId,
            ]
        );
    }

    public function delete(int $roleId): int
    {
        // don't delete if this occupation is used in bibrole
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.bibrole
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This role has dependencies.');
        }
        return $this->conn->executeUpdate(
            'DELETE from data.role
            where role.idrole = ?',
            [
                $roleId,
            ]
        );
    }
}
