<?php

namespace App\ObjectStorage;

use stdClass;
use Exception;

use App\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Exceptions\DependencyException;
use App\Model\Acknowledgement;
use App\ObjectStorage\ManuscriptManager;
use App\ObjectStorage\OccurrenceManager;
use App\ObjectStorage\TypeManager;

/**
 * ObjectManager for acknowledgements
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
        return ArrayToJson::arrayToShortJson($this->getAll());
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

            // update Elastic manuscripts
            $this->container->get(ManuscriptManager::class)->updateElasticAcknowledgement(
                $this->container->get(ManuscriptManager::class)->getAcknowledgementDependencies($id, 'getId')
            );

            // update Elastic occurrences
            $this->container->get(OccurrenceManager::class)->updateElasticAcknowledgement(
                $this->container->get(OccurrenceManager::class)->getAcknowledgementDependencies($id, 'getId')
            );

            // update Elastic types
            $this->container->get(TypeManager::class)->updateElasticAcknowledgement(
                $this->container->get(TypeManager::class)->getAcknowledgementDependencies($id, 'getId')
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
