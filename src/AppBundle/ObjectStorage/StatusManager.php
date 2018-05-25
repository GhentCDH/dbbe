<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Status;

class StatusManager extends ObjectManager
{
    public function getStatusesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'status');
        if (empty($ids)) {
            return $cached;
        }

        $statuses = [];
        $rawStatuses = $this->dbs->getStatusesByIds($ids);

        foreach ($rawStatuses as $rawStatus) {
            $statuses[$rawStatus['status_id']] = new Status(
                $rawStatus['status_id'],
                $rawStatus['status_name'],
                $rawStatus['status_type']
            );
        }

        $this->setCache($statuses, 'status');

        return $cached + $statuses;
    }

    public function getAllStatuses(): array
    {
        $cache = $this->cache->getItem('statuses');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawStatuses = $this->dbs->getAllStatuses();
        $statuses = [];
        foreach ($rawStatuses as $rawStatus) {
            $statuses[] = new Status($rawStatus['status_id'], $rawStatus['status_name'], $rawStatus['status_type']);
        }

        // Sort by name
        usort($statuses, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['statuses']);
        $this->cache->save($cache->set($statuses));
        return $statuses;
    }

    public function getAllManuscriptStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == 'manuscript';
        });
    }

    public function addStatus(stdClass $data): Status
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'type')
                && is_string($data->type)
                && in_array($data->type, ['manuscript', 'occurrence_text', 'occurrence_record', 'type_text'])
            ) {
                $statusId = $this->dbs->insert(
                    $data->name,
                    $data->type
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $newStatus = $this->getStatusesByIds([$statusId])[$statusId];

            $this->updateModified(null, $newStatus);

            // update cache
            $this->cache->invalidateTags(['statuses']);
            $this->setCache([$newStatus->getId() => $newStatus], 'status');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newStatus;
    }

    public function updateStatus(int $statusId, stdClass $data): Status
    {
        $this->dbs->beginTransaction();
        try {
            $statuses = $this->getStatusesByIds([$statusId]);
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
            $this->cache->invalidateTags(['status.' . $statusId, 'statuses']);
            $this->cache->deleteItem('status.' . $statusId);
            $newStatus = $this->getStatusesByIds([$statusId])[$statusId];

            $this->updateModified($status, $newStatus);

            // update cache
            $this->setCache([$newStatus->getId() => $newStatus], 'status');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newStatus;
    }

    public function delStatus(int $statusId): void
    {
        $this->dbs->beginTransaction();
        try {
            $statuses = $this->getStatusesByIds([$statusId]);
            if (count($statuses) == 0) {
                throw new NotFoundHttpException('Status with id ' . $statusId .' not found.');
            }
            $status = $statuses[$statusId];

            $this->dbs->delete($statusId);

            // clear cache
            $this->cache->invalidateTags(['status.' . $statusId, 'statuses']);
            $this->cache->deleteItem('status.' . $statusId);

            $this->updateModified($status, null);

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
