<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Status;
use AppBundle\Utils\ArrayToJson;

class StatusManager extends ObjectManager
{
    public function get(array $ids)
    {
        $rawStatuses = $this->dbs->getStatusesByIds($ids);
        return $this->getWithData($rawStatuses);
    }

    public function getWithData(array $data)
    {
        $statuses = [];
        foreach ($data as $rawStatus) {
            if (isset($rawStatus['status_id']) && !isset($statuses[$rawStatus['status_id']])) {
                $statuses[$rawStatus['status_id']] = new Status(
                    $rawStatus['status_id'],
                    $rawStatus['status_name'],
                    $rawStatus['status_type']
                );
            }
        }

        return $statuses;
    }

    public function getAllJson(): array
    {
        $rawStatuses = $this->dbs->getAllStatuses();
        $statuses = [];
        foreach ($rawStatuses as $rawStatus) {
            $statuses[] = new Status($rawStatus['status_id'], $rawStatus['status_name'], $rawStatus['status_type']);
        }

        // Sort by name
        usort($statuses, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return ArrayToJson::arrayToJson($statuses);
    }

    public function getByTypeShortJson(string $type): array
    {
        return $this->wrapArrayTypeCache(
            'statuses',
            $type,
            ['statuses'],
            function ($type) {
                $rawStatuses = $this->dbs->getStatusesByType($type);
                $statuses = $this->getWithData($rawStatuses);

                return ArrayToJson::arrayToShortJson($statuses);
            }
        );
    }

    public function getByTypeJson(string $type): array
    {
        return $this->wrapArrayTypeCache(
            'statuses_full',
            $type,
            ['statuses'],
            function ($type) {
                $rawStatuses = $this->dbs->getStatusesByType($type);
                $statuses = $this->getWithData($rawStatuses);

                return ArrayToJson::arrayToJson($statuses);
            }
        );
    }

    public function add(stdClass $data): Status
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'type')
                && is_string($data->type)
                && in_array($data->type, [
                    Status::MANUSCRIPT,
                    Status::OCCURRENCE_DIVIDED,
                    Status::OCCURRENCE_RECORD,
                    Status::OCCURRENCE_TEXT,
                    Status::OCCURRENCE_SOURCE,
                    Status::TYPE_TEXT,
                    Status::TYPE_CRITICAL,
                ])
            ) {
                $statusId = $this->dbs->insert(
                    $data->name,
                    $data->type
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $newStatus = $this->get([$statusId])[$statusId];

            $this->updateModified(null, $newStatus);

            $this->cache->invalidateTags(['statuses']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newStatus;
    }

    public function update(int $statusId, stdClass $data): Status
    {
        $this->dbs->beginTransaction();
        try {
            $statuses = $this->get([$statusId]);
            if (count($statuses) == 0) {
                throw new NotFoundHttpException('Status with id ' . $statusId .' not found.');
            }
            $status = $statuses[$statusId];

            // update status data
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($statusId, $data->name);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new status data
            $newStatus = $this->get([$statusId])[$statusId];

            $this->updateModified($status, $newStatus);

            $this->cache->invalidateTags(['statuses']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newStatus;
    }

    public function delete(int $statusId): void
    {
        $this->dbs->beginTransaction();
        try {
            $statuses = $this->get([$statusId]);
            if (count($statuses) == 0) {
                throw new NotFoundHttpException('Status with id ' . $statusId .' not found.');
            }
            $status = $statuses[$statusId];

            $this->dbs->delete($statusId);

            $this->updateModified($status, null);

            $this->cache->invalidateTags(['statuses']);

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
