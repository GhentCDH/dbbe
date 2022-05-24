<?php

namespace App\ObjectStorage;

use Exception;
use stdClass;

use App\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Exceptions\DependencyException;
use App\Model\Management;

class ManagementManager extends ObjectManager
{
    public function get(array $ids)
    {
        $rawManagements = $this->dbs->getManagementsByIds($ids);
        return $this->getWithData($rawManagements);
    }

    public function getWithData(array $data)
    {
        $managements = [];
        foreach ($data as $rawManagement) {
            if (isset($rawManagement['management_id']) && !isset($managements[$rawManagement['management_id']])) {
                $managements[$rawManagement['management_id']] = new Management(
                    $rawManagement['management_id'],
                    $rawManagement['management_name']
                );
            }
        }

        return $managements;
    }

    public function getAll(): array
    {
        $rawManagements = $this->dbs->getAllManagements();
        $managements = $this->getWithData($rawManagements);

        // Sort by name
        usort($managements, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $managements;
    }

    public function getAllShortJson(): array
    {
        return ArrayToJson::arrayToShortJson($this->getAll());
    }

    public function getAllJson(): array
    {
        return ArrayToJson::arrayToJson($this->getAll());
    }

    public function add(stdClass $data): Management
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && !empty($data->name)
            ) {
                $id = $this->dbs->insert(
                    $data->name
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified(null, $new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    public function update(int $id, stdClass $data): Management
    {
        $this->dbs->beginTransaction();
        try {
            $managements = $this->get([$id]);
            if (count($managements) == 0) {
                throw new NotFoundHttpException('Management with id ' . $id .' not found.');
            }
            $old = $managements[$id];

            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
                && !empty($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($id, $data->name);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);

            // update Elastic dependencies
            foreach (array_values(ElasticManagers::MANAGERS) as $manager) {
                $this->container->get($manager)->updateElasticManagement(
                    $this->container->get($manager)->getManagementDependencies($id, 'getId')
                );
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();


            // reset Elastic dependencies
            foreach (array_values(ElasticManagers::MANAGERS) as $manager) {
                $this->container->get($manager)->updateElasticManagement(
                    $this->container->get($manager)->getManagementDependencies($id, 'getId')
                );
            }

            throw $e;
        }

        return $new;
    }

    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $managements = $this->get([$id]);
            if (count($managements) == 0) {
                throw new NotFoundHttpException('Management with id ' . $id .' not found.');
            }
            $old = $managements[$id];

            // get dependencies
            $dependencies = [];
            foreach (array_values(ElasticManagers::MANAGERS) as $manager) {
                $ids = $this->container->get($manager)->getManagementDependencies($id, 'getId');
                if (!empty($ids)) {
                    $dependencies[$entity] = $ids;
                }
            }

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            // update dependencies
            foreach ($dependencies as $entityType => $ids) {
                $this->container->get(ElasticManagers::MANAGERS[$entityType])->updateElasticManagement($ids);
            }

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
