<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Meter;

/**
 * ObjectManager for meters
 * Servicename: meter_manager
 */
class MeterManager extends ObjectManager
{
    /**
     * Get meters with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Meter::CACHENAME,
            $ids,
            function ($ids) {
                $meters = [];
                $rawMeters = $this->dbs->getMetersByIds($ids);
                $meters = $this->getWithData($rawMeters);

                return $meters;
            }
        );
    }

    /**
     * Get meters with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            Meter::CACHENAME,
            $data,
            'meter_id',
            function ($data) {
                $meters = [];
                foreach ($data as $rawMeter) {
                    if (isset($rawMeter['meter_id']) && !isset($meters[$rawMeter['meter_id']])) {
                        $meters[$rawMeter['meter_id']] = new Meter(
                            $rawMeter['meter_id'],
                            $rawMeter['name']
                        );
                    }
                }

                return $meters;
            }
        );
    }

    /**
     * Get all meters with all information
     * @return array
     */
    public function getAll(): array
    {
        return $this->wrapArrayCache(
            'meters',
            ['meters'],
            function () {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, 'meter_id');
                $meters = $this->get($ids);

                // Sort by name
                usort($meters, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $meters;
            }
        );
    }

    /**
     * Clear cache
     * @param array $ids
     */
    public function reset(array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteCache(Meter::CACHENAME, $id);
        }

        $this->get($ids);

        $this->cache->invalidateTags(['meters']);
    }

    /**
     * Add a new meter
     * @param  stdClass $data
     * @return Meter
     */
    public function add(stdClass $data): Meter
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $id = $this->dbs->insert($data->name);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified(null, $new);

            // update cache
            $this->cache->invalidateTags(['meters']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing meter
     * @param  int      $id
     * @param  stdClass $data
     * @return Meter
     */
    public function update(int $id, stdClass $data): Meter
    {
        $this->dbs->beginTransaction();
        try {
            $meters = $this->get([$id]);
            if (count($meters) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Meter with id ' . $id .' not found.');
            }
            $old = $meters[$id];

            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $this->dbs->updateName($id, $data->name);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $this->deleteCache(Meter::CACHENAME, $id);
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);

            // update Elastic occurrences
            $occurrences = $this->container->get('occurrence_manager')->getMeterDependencies($id, true);
            $this->container->get('occurrence_manager')->elasticIndex($occurrences);

            // update Elastic types
            $types = $this->container->get('type_manager')->getMeterDependencies($id, true);
            $this->container->get('type_manager')->elasticIndex($types);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Delete a meter
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $meters = $this->get([$id]);
            if (count($meters) == 0) {
                throw new NotFoundHttpException('Meter with id ' . $id .' not found.');
            }
            $old = $meters[$id];

            $this->dbs->delete($id);

            // empty cache
            $this->deleteCache(Meter::CACHENAME, $id);
            $this->cache->invalidateTags(['meters']);

            $this->updateModified($old, null);

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
