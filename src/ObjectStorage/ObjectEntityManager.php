<?php

namespace App\ObjectStorage;

use Exception;
use stdClass;

use App\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Exceptions\DependencyException;
use App\Model\Entity;

abstract class  ObjectEntityManager extends EntityManager
{
    use UpdateElasticByIdsTrait;
    use DeleteElasticByIdIfExistsTrait;

    abstract public function getMini(array $ids): array;
    abstract public function getShort(array $ids): array;
    abstract public function getFull(int $id);

    public function getAllSitemap(string $sortFunction = null): array
    {
        return $this->getAllCombined('sitemap', $sortFunction);
    }

    public function getAllMicroShortJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedShortJson('micro', $sortFunction);
    }

    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedShortJson('mini', $sortFunction);
    }

    public function getAllShort(string $sortFunction = null): array
    {
        return $this->getAllCombined('short', $sortFunction);
    }

    protected function getAllCombined(string $level, string $sortFunction = null): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, $this->entityType . '_id');

        $objects = [];
        switch ($level) {
            case 'mini':
                $objects = $this->getMini($ids);
                break;
            case 'short':
                $objects = $this->getShort($ids);
                break;
            case 'sitemap':
                $objects = $this->getSitemap($ids);
                break;
        }

        if (!empty($sortFunction)) {
            usort($objects, function ($a, $b) use ($sortFunction) {
                if ($sortFunction == 'getId') {
                    return $a->getId() <=> $b->getId();
                }
                return strcmp($a->{$sortFunction}(), $b->{$sortFunction}());
            });
        }

        return $objects;
    }

    public function addManagements(stdClass $data): void
    {
        if (!property_exists($data, 'ids') && !property_exists($data, 'filter')
        ) {
            throw new BadRequestHttpException('Incorrect data.');
        }
        if (property_exists($data, 'filter')) {
            if (!is_object($data->filter)) {
                throw new BadRequestHttpException('Incorrect filter data.');
            }
            $data->ids = $this->ess->getAllResults($data->filter);
        }
        if (property_exists($data, 'ids')) {
            if (!is_array($data->ids)) {
                throw new BadRequestHttpException('Incorrect ids data.');
            }
            foreach ($data->ids as $id) {
                if (!is_numeric($id)) {
                    throw new BadRequestHttpException('Incorrect id data.');
                }
            }
        }
        if (!property_exists($data, 'managements')
            || !is_array($data->managements)
        ) {
            throw new BadRequestHttpException('Incorrect managements data.');
        }
        foreach ($data->managements as $management) {
            if (!is_object($management)
                || !property_exists($management, 'id')
                || !is_numeric($management->id)
            ) {
                throw new BadRequestHttpException('Incorrect management data.');
            }
        }

        $entities = $this->getShort($data->ids);

        $addIds = [];
        foreach ($entities as $entity) {
            foreach ($data->managements as $newMan) {
                $exists = false;

                foreach ($entity->getManagements() as $oldMan) {
                    if ($newMan->id == $oldMan->getId()) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    if (!isset($addIds[$entity->getId()])) {
                        $addIds[$entity->getId()] = [];
                    }
                    $addIds[$entity->getId()][] = $newMan->id;
                }
            }
        }

        // Update entities
        if (!empty($addIds)) {
            $this->dbs->beginTransaction();
            try {
                // update management collections
                foreach ($addIds as $entityId => $ids) {
                    foreach ($ids as $id) {
                        $this->dbs->addManagement($entityId, $id);
                    }
                }

                // update log with minimal data (id, managements)
                $rawManagements = $this->dbs->getManagements(array_keys($addIds));
                $newEntities = [];
                foreach (array_keys($addIds) as $id) {
                    $newEntities[$id] = (new Entity())
                        ->setId($id);
                }
                if (!empty($rawManagements)) {
                    $managements = $this->container->get(ManagementManager::class)->getWithData($rawManagements);

                    foreach ($rawManagements as $rawManagement) {
                        $newEntities[$rawManagement['entity_id']]
                            ->addManagement($managements[$rawManagement['management_id']]);
                    }
                }

                foreach ($newEntities as $newEntity) {
                    $old = (new Entity())
                        ->setId($newEntity->getId())
                        ->setManagements($entities[$newEntity->getId()]->getManagements());
                    $this->updateModified($old, $newEntity);
                }

                // update data to update elastic search
                $esData = [];
                foreach ($newEntities as $newEntity) {
                    $esData[$newEntity->getId()] = [
                        'id' => $newEntity->getId(),
                        'management' => ArrayToJson::arrayToShortJson($newEntity->getManagements()),
                    ];
                }
                $this->ess->updateMultiple($esData);

                // commit transaction
                $this->dbs->commit();
            } catch (\Exception $e) {
                $this->dbs->rollBack();

                throw $e;
            }
        }
    }

    public function removeManagements(stdClass $data): void
    {
        if (!property_exists($data, 'ids') && !property_exists($data, 'filter')
        ) {
            throw new BadRequestHttpException('Incorrect data.');
        }
        if (property_exists($data, 'filter')) {
            if (!is_object($data->filter)) {
                throw new BadRequestHttpException('Incorrect filter data.');
            }
            $data->ids = $this->ess->getAllResults($data->filter);
        }
        if (property_exists($data, 'ids')) {
            if (!is_array($data->ids)) {
                throw new BadRequestHttpException('Incorrect ids data.');
            }
            foreach ($data->ids as $id) {
                if (!is_numeric($id)) {
                    throw new BadRequestHttpException('Incorrect id data.');
                }
            }
        }
        if (!property_exists($data, 'managements')
            || !is_array($data->managements)
        ) {
            throw new BadRequestHttpException('Incorrect managements data.');
        }
        foreach ($data->managements as $management) {
            if (!is_object($management)
                || !property_exists($management, 'id')
                || !is_numeric($management->id)
            ) {
                throw new BadRequestHttpException('Incorrect management data.');
            }
        }

        $entities = $this->getShort($data->ids);

        $delIds = [];
        foreach ($entities as $entity) {
            foreach ($data->managements as $newMan) {
                $exists = false;

                foreach ($entity->getManagements() as $oldMan) {
                    if ($newMan->id == $oldMan->getId()) {
                        $exists = true;
                        break;
                    }
                }

                if ($exists) {
                    if (!isset($delIds[$entity->getId()])) {
                        $delIds[$entity->getId()] = [];
                    }
                    $delIds[$entity->getId()][] = $newMan->id;
                }
            }
        }

        // Update entities
        if (!empty($delIds)) {
            $this->dbs->beginTransaction();
            try {
                // update management collections
                foreach ($delIds as $entityId => $ids) {
                    $this->dbs->delManagements($entityId, $ids);
                }

                // update log with minimal data (id, managements)
                $rawManagements = $this->dbs->getManagements(array_keys($delIds));
                $newEntities = [];
                foreach (array_keys($delIds) as $id) {
                    $newEntities[$id] = (new Entity())
                        ->setId($id);
                }
                if (!empty($rawManagements)) {
                    $managements = $this->container->get(ManagementManager::class)->getWithData($rawManagements);

                    foreach ($rawManagements as $rawManagement) {
                        $newEntities[$rawManagement['entity_id']]
                            ->addManagement($managements[$rawManagement['management_id']]);
                    }
                }

                foreach ($newEntities as $newEntity) {
                    $old = (new Entity())
                        ->setId($newEntity->getId())
                        ->setManagements($entities[$newEntity->getId()]->getManagements());
                    $this->updateModified($old, $newEntity);
                }

                // update data to update elastic search
                $esData = [];
                foreach ($newEntities as $newEntity) {
                    $esData[$newEntity->getId()] = [
                        'id' => $newEntity->getId(),
                        'management' => ArrayToJson::arrayToShortJson($newEntity->getManagements()),
                    ];
                }
                $this->ess->updateMultiple($esData);

                // commit transaction
                $this->dbs->commit();
            } catch (\Exception $e) {
                $this->dbs->rollBack();

                throw $e;
            }
        }
    }

    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            // remove from elasticsearch
            $this->deleteElasticByIdIfExists($id);

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