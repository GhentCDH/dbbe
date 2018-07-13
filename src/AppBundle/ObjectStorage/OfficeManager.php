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
    public function getOfficesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'office');
        if (empty($ids)) {
            return $cached;
        }

        $offices = [];
        $rawOffices = $this->dbs->getOfficesByIds($ids);

        foreach ($rawOffices as $rawOffice) {
            $offices[$rawOffice['office_id']] = new Office(
                $rawOffice['office_id'],
                $rawOffice['name']
            );
        }

        $this->setCache($offices, 'office');

        return $cached + $offices;
    }

    public function getAllOffices(): array
    {
        $cache = $this->cache->getItem('offices');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $offices = [];
        $rawOffices = $this->dbs->getAllOffices();

        foreach ($rawOffices as $rawOffice) {
            $offices[] = new Office(
                $rawOffice['office_id'],
                $rawOffice['name']
            );
        }

        // Sort by name
        usort($offices, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['offices']);
        $this->cache->save($cache->set($offices));
        return $offices;
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
            $newOffice = $this->getOfficesByIds([$officeId])[$officeId];

            $this->updateModified(null, $newOffice);

            // update cache
            $this->cache->invalidateTags(['offices']);
            $this->setCache([$newOffice->getId() => $newOffice], 'office');

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
            $offices = $this->getOfficesByIds([$officeId]);
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
            $this->cache->invalidateTags(['office.' . $officeId, 'offices']);
            $this->cache->deleteItem('office.' . $officeId);
            $newOffice = $this->getOfficesByIds([$officeId])[$officeId];

            $this->updateModified($office, $newOffice);

            // update cache
            $this->setCache([$newOffice->getId() => $newOffice], 'office');

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
            $offices = $this->getOfficesByIds([$officeId]);
            if (count($offices) == 0) {
                throw new NotFoundHttpException('Office with id ' . $officeId .' not found.');
            }
            $office = $offices[$officeId];

            $this->dbs->delete($officeId);

            // clear cache
            $this->cache->invalidateTags(['office.' . $officeId, 'offices']);
            $this->cache->deleteItem('office.' . $officeId);

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
