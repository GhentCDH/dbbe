<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Metre;

/**
 * ObjectManager for metres
 * Servicename: metre_manager
 */
class MetreManager extends ObjectManager
{
    /**
     * Get metres with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $rawMetres = $this->dbs->getMetresByIds($ids);
        return $this->getWithData($rawMetres);
    }

    /**
     * Get metres with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        $metres = [];
        foreach ($data as $rawMetre) {
            if (isset($rawMetre['metre_id']) && !isset($metres[$rawMetre['metre_id']])) {
                $metres[$rawMetre['metre_id']] = new Metre(
                    $rawMetre['metre_id'],
                    $rawMetre['name']
                );
            }
        }

        return $metres;
    }

    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'metre_id');
        $metres = $this->get($ids);

        // Sort by name
        usort($metres, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $metres;
    }

    /**
     * Get all metres with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'metres',
            ['metres'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getAll());
            }
        );
    }

    /**
     * Get all metres with all information
     * @return array
     */
    public function getAllJson(): array
    {
        return ArrayToJson::arrayToJson($this->getAll());
    }

    /**
     * Add a new metre
     * @param  stdClass $data
     * @return Metre
     */
    public function add(stdClass $data): Metre
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

            $this->cache->invalidateTags(['metres']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing metre
     * @param  int      $id
     * @param  stdClass $data
     * @return Metre
     */
    public function update(int $id, stdClass $data): Metre
    {
        $this->dbs->beginTransaction();
        try {
            $metres = $this->get([$id]);
            if (count($metres) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Metre with id ' . $id .' not found.');
            }
            $old = $metres[$id];

            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $this->dbs->updateName($id, $data->name);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['metres']);

            // update Elastic occurrences
            $this->container->get('occurrence_manager')->updateElasticMetre(
                $this->container->get('occurrence_manager')->getMetreDependencies($id, 'getId')
            );

            // update Elastic types
            $this->container->get('type_manager')->updateElasticMetre(
                $this->container->get('type_manager')->getMetreDependencies($id, 'getId')
            );

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Delete a metre
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $metres = $this->get([$id]);
            if (count($metres) == 0) {
                throw new NotFoundHttpException('Metre with id ' . $id .' not found.');
            }
            $old = $metres[$id];

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            $this->cache->invalidateTags(['metres']);

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
