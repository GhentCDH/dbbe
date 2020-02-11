<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Acknowledgement;

/**
 * ObjectManager for acknowledgements
 * Servicename: acknowledgement_manager
 */
class AcknowledgementManager extends ObjectManager
{
    /**
     * Get acknowledgements with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $rawAcknowledgements = $this->dbs->getAcknowledgementsByIds($ids);
        return $this->getWithData($rawAcknowledgements);
    }

    /**
     * Get acknowledgements with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        $acknowledgements = [];
        foreach ($data as $rawAcknowledgement) {
            if (isset($rawAcknowledgement['acknowledgement_id'])
                && !isset($acknowledgements[$rawAcknowledgement['acknowledgement_id']])
            ) {
                $acknowledgements[$rawAcknowledgement['acknowledgement_id']] = new Acknowledgement(
                    $rawAcknowledgement['acknowledgement_id'],
                    $rawAcknowledgement['name']
                );
            }
        }
        return $acknowledgements;
    }

    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'acknowledgement_id');
        $acknowledgements = $this->get($ids);

        // Sort by name
        usort($acknowledgements, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $acknowledgements;
    }

    /**
     * Get all acknowledgements with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'acknowledgements',
            ['acknowledgements'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getAll());
            }
        );
    }

    /**
     * Get all acknowledgements with all information
     * @return array
     */
    public function getAllJson(): array
    {
        return ArrayToJson::arrayToJson($this->getAll());
    }

    /**
     * Add a new acknowledgement
     * @param  stdClass $data
     * @return Acknowledgement
     */
    public function add(stdClass $data): Acknowledgement
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

            $this->cache->invalidateTags(['acknowledgements']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing acknowledgement
     * @param  int      $id
     * @param  stdClass $data
     * @return Acknowledgement
     */
    public function update(int $id, stdClass $data): Acknowledgement
    {
        $this->dbs->beginTransaction();
        try {
            $acknowledgements = $this->get([$id]);
            if (count($acknowledgements) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Acknowledgement with id ' . $id .' not found.');
            }
            $old = $acknowledgements[$id];

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

            $this->cache->invalidateTags(['acknowledgements']);

            // update Elastic manuscripts
            $this->container->get('manuscript_manager')->updateElasticAcknowledgement(
                $this->container->get('manuscript_manager')->getAcknowledgementDependencies($id, 'getId')
            );

            // update Elastic occurrences
            $this->container->get('occurrence_manager')->updateElasticAcknowledgement(
                $this->container->get('occurrence_manager')->getAcknowledgementDependencies($id, 'getId')
            );

            // update Elastic types
            $this->container->get('type_manager')->updateElasticAcknowledgement(
                $this->container->get('type_manager')->getAcknowledgementDependencies($id, 'getId')
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
     * Delete a acknowledgement
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $acknowledgements = $this->get([$id]);
            if (count($acknowledgements) == 0) {
                throw new NotFoundHttpException('Acknowledgement with id ' . $id .' not found.');
            }
            $old = $acknowledgements[$id];

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            $this->cache->invalidateTags(['acknowledgements']);

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
