<?php

namespace App\ObjectStorage;

use Exception;
use stdClass;

use App\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\ElasticSearchService\ElasticBibliographyService;
use App\ElasticSearchService\ElasticManuscriptService;
use App\ElasticSearchService\ElasticOccurrenceService;
use App\ElasticSearchService\ElasticTypeService;
use App\Exceptions\DependencyException;
use App\Model\Role;

class RoleManager extends ObjectManager
{
    public function get(array $ids)
    {
        $rawRoles = $this->dbs->getRolesByIds($ids);
        return $this->getWithData($rawRoles);
    }

    public function getWithData(array $data)
    {
        $roles = [];
        foreach ($data as $rawRole) {
            if (isset($rawRole['role_id']) && !isset($roles[$rawRole['role_id']])) {
                $roles[$rawRole['role_id']] = new Role(
                    $rawRole['role_id'],
                    json_decode($rawRole['role_usage']),
                    $rawRole['role_system_name'],
                    $rawRole['role_name'],
                    $rawRole['role_is_contributor_role'] ? true : false,
                    $rawRole['role_has_rank'] ? true : false,
                    $rawRole['role_order']
                );
            }
        }

        return $roles;
    }

    public function getAllRolesJson(): array
    {
        $roles = [];
        $rawRoles = $this->dbs->getAllRoles();
        $roles = $this->getWithData($rawRoles);

        // Sort by name
        usort($roles, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return ArrayToJson::arrayToJson($roles);
    }

    public function getByType(string $type): array
    {
        $roles = [];
        $rawRoles = $this->dbs->getByType($type);

        // Keys in this array must be systemnames as they are used in queries
        $rolesWithId = $this->getWithData($rawRoles);
        foreach ($rolesWithId as $roleWithId) {
            $roles[$roleWithId->getSystemName()] = $roleWithId;
        }

        return $roles;
    }

    public function getContributorByType(string $type): array
    {
        $roles = [];
        $rawRoles = $this->dbs->getContributorByType($type);

        // Keys in this array must be systemnames as they are used in queries
        $rolesWithId = $this->getWithData($rawRoles);
        foreach ($rolesWithId as $roleWithId) {
            $roles[$roleWithId->getSystemName()] = $roleWithId;
        }

        return $roles;
    }

    public function getByTypeJson(string $type): array
    {
        return ArrayToJson::arrayToJson($this->getByType($type));
    }

    public function getContributorByTypeJson(string $type): array
    {
        return ArrayToJson::arrayToJson($this->getContributorByType($type));
    }

    public function add(stdClass $data): Role
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'usage')
                && is_array($data->usage)
                && !empty($data->usage)
                && property_exists($data, 'systemName')
                && is_string($data->systemName)
                && !empty($data->systemName)
                && property_exists($data, 'name')
                && is_string($data->name)
                && !empty($data->name)
                && !(
                    property_exists($data, 'contributorRole')
                    && !($data->contributorRole == null || is_bool($data->contributorRole))
                )
                && !(
                    property_exists($data, 'rank')
                    && !($data->rank == null || is_bool($data->rank))
                )
            ) {
                foreach ($data->usage as $usagePart) {
                    if (!is_string($usagePart)) {
                        throw new BadRequestHttpException('Incorrect data.');
                    }
                }
                $roleId = $this->dbs->insert(
                    $data->usage,
                    $data->systemName,
                    $data->name,
                    property_exists($data, 'contributorRole') ? $data->contributorRole : false,
                    property_exists($data, 'rank') ? $data->rank : false
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $newRole = $this->get([$roleId])[$roleId];

            $this->updateModified(null, $newRole);

            $this->updateRoleMapping();

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRole;
    }

    public function update(int $roleId, stdClass $data): Role
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
                && !empty($data->usage)
            ) {
                $correct = true;
                $this->updateUsage($role, $data->usage);
            }
            if (property_exists($data, 'name')
                && is_string($data->name)
                && !empty($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($roleId, $data->name);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new role data
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

    private function updateUsage(Role $role, array $usage)
    {
        foreach ($usage as $usagePart) {
            if (!is_string($usagePart)) {
                throw new BadRequestHttpException('Incorrect usage data.');
            }
        }

        $del = array_diff($role->getUsage(), $usage);
        $add = array_diff($usage, $role->getUsage());

        foreach ($del as $entityType) {
            if ($entityType == 'bookChapter') {
                $entityType = 'book_chapter';
            }
            $depIds = $this->container->get(ElasticManagers::MANAGERS[$entityType])->getRoleDependencies($role->getId(), 'getId');
            if (!empty($depIds)) {
                throw new BadRequestHttpException('Dependency error.');
            }
        }

        $this->dbs->updateUsage($role->getId(), $usage);

        $this->updateRoleMapping();
    }

    public function delete(int $roleId): void
    {
        $this->dbs->beginTransaction();
        try {
            $roles = $this->get([$roleId]);
            if (count($roles) == 0) {
                throw new NotFoundHttpException('Role with id ' . $roleId .' not found.');
            }
            $role = $roles[$roleId];

            $this->dbs->delete($roleId);

            $this->updateModified($role, null);

            // fields cannot be removed from mapping, so don't update mapping

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

    private function updateRoleMapping(): void
    {
        foreach ([
            ElasticBibliographyService::class,
            ElasticManuscriptService::class,
            ElasticOccurrenceService::class,
            ElasticTypeService::class,
        ] as $service) {
            $this->container->get($service)->updateRoleMapping();
        }
    }
}
