<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use AppBundle\Exceptions\DependencyException;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Role;

class RoleManager extends ObjectManager
{
    public function getRolesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'role');
        if (empty($ids)) {
            return $cached;
        }

        $roles = [];
        $rawRoles = $this->dbs->getRolesByIds($ids);

        foreach ($rawRoles as $rawRole) {
            $roles[$rawRole['role_id']] = new Role(
                $rawRole['role_id'],
                json_decode($rawRole['usage']),
                $rawRole['system_name'],
                $rawRole['name']
            );
        }

        $this->setCache($roles, 'role');

        return $cached + $roles;
    }

    public function getAllRoles(): array
    {
        $cache = $this->cache->getItem('roles');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $roles = [];
        $rawRoles = $this->dbs->getAllRoles();

        foreach ($rawRoles as $rawRole) {
            $roles[] = new Role(
                $rawRole['role_id'],
                json_decode($rawRole['usage']),
                $rawRole['system_name'],
                $rawRole['name']
            );
        }

        // Sort by name
        usort($roles, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['roles']);
        $this->cache->save($cache->set($roles));
        return $roles;
    }

    public function getRolesByType(string $type): array
    {
        $cache = $this->cache->getItem('role.' . $type);
        if ($cache->isHit()) {
            return $cache->get();
        }

        $roles = [];
        $rawRoles = $this->dbs->getRolesByType($type);

        foreach ($rawRoles as $rawRole) {
            $roles[$rawRole['system_name']] = new Role(
                $rawRole['role_id'],
                json_decode($rawRole['usage']),
                $rawRole['system_name'],
                $rawRole['name']
            );
        }

        $cache->tag(['roles']);
        $this->cache->save($cache->set($roles));
        return $roles;
    }

    public function addRole(stdClass $data): Role
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'usage')
                && is_array($data->usage)
                && property_exists($data, 'systemName')
                && is_string($data->systemName)
                && property_exists($data, 'name')
                && is_string($data->name)
            ) {
                foreach ($data->usage as $usagePart) {
                    if (!is_string($usagePart)) {
                        throw new BadRequestHttpException('Incorrect data.');
                    }
                }
                $roleId = $this->dbs->insert(
                    $data->usage,
                    $data->systemName,
                    $data->name
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $newRole = $this->getRolesByIds([$roleId])[$roleId];

            $this->updateModified(null, $newRole);

            // update cache
            $this->cache->invalidateTags(['roles']);
            $this->setCache([$newRole->getId() => $newRole], 'role');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRole;
    }

    public function updateRole(int $roleId, stdClass $data): Role
    {
        $this->dbs->beginTransaction();
        try {
            $roles = $this->getRolesByIds([$roleId]);
            if (count($roles) == 0) {
                throw new NotFoundHttpException('Role with id ' . $roleId .' not found.');
            }
            $role = $roles[$roleId];

            // update role data
            $correct = false;
            if (property_exists($data, 'usage')
                && is_array($data->usage)
            ) {

                foreach ($data->usage as $usagePart) {
                    if (!is_string($usagePart)) {
                        throw new BadRequestHttpException('Incorrect data.');
                    }
                }
                $correct = true;
                $this->dbs->updateUsage($roleId, $data->usage);
            }
            if (property_exists($data, 'systemName')
                && is_string($data->systemName)
            ) {
                $correct = true;
                $this->dbs->updateSystemName($roleId, $data->systemName);
            }
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($roleId, $data->name);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new role data
            $this->cache->invalidateTags(['role.' . $roleId, 'roles']);
            $this->cache->deleteItem('role.' . $roleId);
            $newRole = $this->getRolesByIds([$roleId])[$roleId];

            $this->updateModified($role, $newRole);

            // update cache
            $this->setCache([$newRole->getId() => $newRole], 'role');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRole;
    }

    public function delRole(int $roleId): void
    {
        $this->dbs->beginTransaction();
        try {
            $roles = $this->getRolesByIds([$roleId]);
            if (count($roles) == 0) {
                throw new NotFoundHttpException('Role with id ' . $roleId .' not found.');
            }
            $role = $roles[$roleId];

            $this->dbs->delete($roleId);

            // clear cache
            $this->cache->invalidateTags(['role.' . $roleId, 'roles']);
            $this->cache->deleteItem('role.' . $roleId);

            $this->updateModified($role, null);

            // commit transaction
            $this->dbs->commit();
        } catch (DependencyException $e) {
            $this->dbs->rollBack();
            throw new BadRequestHttpException($e->getMessage());
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return;
    }
}
