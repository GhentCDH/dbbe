<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use AppBundle\Exceptions\DependencyException;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Office;

class OfficeManager extends ObjectManager
{
    public function get(array $ids)
    {
        return $this->wrapCache(
            Office::CACHENAME,
            $ids,
            function ($ids) {
                $offices = [];
                $rawOffices = $this->dbs->getOfficesByIds($ids);
                $offices = $this->getWithData($rawOffices);

                return $offices;
            }
        );
    }

    public function getWithData(array $data)
    {
        return $this->wrapDataCache(
            Office::CACHENAME,
            $data,
            'office_id',
            function ($data) {
                $offices = [];
                foreach ($data as $rawOffice) {
                    if (isset($rawOffice['office_id'])) {
                        $offices[$rawOffice['office_id']] = new Office(
                            $rawOffice['office_id'],
                            $rawOffice['name']
                        );
                    }
                }

                return $offices;
            }
        );
    }

    public function getAllOffices(): array
    {
        return $this->wrapArrayCache(
            'offices',
            ['offices'],
            function () {
                $offices = [];
                $rawOffices = $this->dbs->getAllOffices();
                $offices = $this->getWithData($rawOffices);

                // Sort by name
                usort($offices, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $offices;
            }
        );
    }

    public function addOffice(stdClass $data): Office
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $officeId = $this->dbs->insert(
                    $data->name
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $newOffice = $this->get([$officeId])[$officeId];

            $this->updateModified(null, $newOffice);

            // update cache
            $this->cache->invalidateTags(['offices']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOffice;
    }

    public function updateOffice(int $officeId, stdClass $data): Office
    {
        $this->dbs->beginTransaction();
        try {
            $offices = $this->get([$officeId]);
            if (count($offices) == 0) {
                throw new NotFoundHttpException('Office with id ' . $officeId .' not found.');
            }
            $office = $offices[$officeId];

            // update office data
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($officeId, $data->name);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new office data
            $this->deleteCache(Office::CACHENAME, $officeId);
            $newOffice = $this->get([$officeId])[$officeId];

            $this->updateModified($office, $newOffice);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOffice;
    }

    public function delOffice(int $officeId): void
    {
        $this->dbs->beginTransaction();
        try {
            $offices = $this->get([$officeId]);
            if (count($offices) == 0) {
                throw new NotFoundHttpException('Office with id ' . $officeId .' not found.');
            }
            $office = $offices[$officeId];

            $this->dbs->delete($officeId);

            // clear cache
            $this->cache->invalidateTags(['offices']);
            $this->deleteCache(Office::CACHENAME, $officeId);

            $this->updateModified($office, null);

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
