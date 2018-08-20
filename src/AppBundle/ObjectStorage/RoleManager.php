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
    public function get(array $ids)
    {
        return $this->wrapCache(
            Role::CACHENAME,
            $ids,
            function ($ids) {
                $roles = [];
                $rawRoles = $this->dbs->getRolesByIds($ids);
                $roles = $this->getWithData($rawRoles);

                return $roles;
            }
        );
    }

    public function getWithData(array $data)
    {
        return $this->wrapDataCache(
            Role::CACHENAME,
            $data,
            'role_id',
            function ($data) {
                $roles = [];
                foreach ($data as $rawRole) {
                    if (isset($rawRole['role_id'])) {
                        $roles[$rawRole['role_id']] = new Role(
                            $rawRole['role_id'],
                            json_decode($rawRole['role_usage']),
                            $rawRole['role_system_name'],
                            $rawRole['role_name']
                        );
                    }
                }

                return $roles;
            }
        );
    }

    public function getAllRoles(): array
    {
        return $this->wrapArrayCache(
            'roles',
            ['roles'],
            function () {
                $roles = [];
                $rawRoles = $this->dbs->getAllRoles();
                $roles = $this->getWithData($rawRoles);

                // Sort by name
                usort($roles, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $roles;
            }
        );
    }

    public function getRolesByType(string $type): array
    {
        return $this->wrapArrayTypeCache(
            'roles',
            $type,
            ['roles'],
            function ($type) {
                $roles = [];
                $rawRoles = $this->dbs->getRolesByType($type);
                $roles = $this->getWithData($rawRoles);

                return $roles;
            }
        );
    }

    // TODO: systemName niet aanpasbaar maken
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
            $newRole = $this->get([$roleId])[$roleId];

            $this->updateModified(null, $newRole);

            // update cache
            $this->cache->invalidateTags(['roles']);

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
            $roles = $this->get([$roleId]);
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
            $this->deleteCache(Role::CACHENAME, $roleId);
            $newRole = $this->get([$roleId])[$roleId];

            $this->updateModified($role, $newRole);

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
            $roles = $this->get([$roleId]);
            if (count($roles) == 0) {
                throw new NotFoundHttpException('Role with id ' . $roleId .' not found.');
            }
            $role = $roles[$roleId];

            $this->dbs->delete($roleId);

            // clear cache
            $this->cache->invalidateTags(['roles']);
            $this->deleteCache(Role::CACHENAME, $roleId);

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
