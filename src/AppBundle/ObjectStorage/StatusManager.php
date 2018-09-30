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
    public function get(array $ids)
    {
        return $this->wrapCache(
            Status::CACHENAME,
            $ids,
            function ($ids) {
                $statuses = [];
                $rawStatuses = $this->dbs->getStatusesByIds($ids);

                foreach ($rawStatuses as $rawStatus) {
                    $statuses[$rawStatus['status_id']] = new Status(
                        $rawStatus['status_id'],
                        $rawStatus['status_name'],
                        $rawStatus['status_type']
                    );
                }

                return $statuses;
            }
        );
    }

    public function getWithData(array $data)
    {
        return $this->wrapDataCache(
            Status::CACHENAME,
            $data,
            'status_id',
            function ($data) {
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
        );
    }

    public function getAllStatuses(): array
    {
        return $this->wrapArrayCache(
            'statuses',
            ['statuses'],
            function () {
                $rawStatuses = $this->dbs->getAllStatuses();
                $statuses = [];
                foreach ($rawStatuses as $rawStatus) {
                    $statuses[] = new Status($rawStatus['status_id'], $rawStatus['status_name'], $rawStatus['status_type']);
                }

                // Sort by name
                usort($statuses, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $statuses;
            }
        );
    }

    public function getAllManuscriptStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == Status::MANUSCRIPT;
        });
    }

    public function getAllOccurrenceTextStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == Status::OCCURRENCE_TEXT;
        });
    }

    public function getAllOccurrenceRecordStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == Status::OCCURRENCE_RECORD;
        });
    }

    public function getAllOccurrenceDividedStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == Status::OCCURRENCE_DIVIDED;
        });
    }

    public function getAllOccurrenceSourceStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == Status::OCCURRENCE_SOURCE;
        });
    }

    public function getAllTypeTextStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == Status::TYPE_TEXT;
        });
    }

    public function getAllTypeCriticalStatuses(): array
    {
        return array_filter($this->getAllStatuses(), function ($status) {
            return $status->getType() == Status::TYPE_CRITICAL;
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

            // update cache
            $this->cache->invalidateTags(['statuses']);

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
            $this->deleteCache(Status::CACHENAME, $statusId);
            $newStatus = $this->get([$statusId])[$statusId];

            $this->updateModified($status, $newStatus);

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
            $statuses = $this->get([$statusId]);
            if (count($statuses) == 0) {
                throw new NotFoundHttpException('Status with id ' . $statusId .' not found.');
            }
            $status = $statuses[$statusId];

            $this->dbs->delete($statusId);

            // clear cache
            $this->cache->invalidateTags(['statuses']);
            $this->deleteCache(Status::CACHENAME, $statusId);

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
