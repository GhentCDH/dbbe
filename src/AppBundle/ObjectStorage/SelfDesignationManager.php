<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\SelfDesignation;
use AppBundle\Utils\GreekNormalizer;

/**
 * ObjectManager for self designations
 * Servicename: self_designation_manager
 */
class SelfDesignationManager extends ObjectManager
{
    /**
     * Get self designations with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $rawSelfDesignations = $this->dbs->getSelfDesignationsByIds($ids);
        return $this->getWithData($rawSelfDesignations);
    }

    /**
     * Get self designations with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        $selfDesignations = [];
        foreach ($data as $rawSelfDesignation) {
            if (isset($rawSelfDesignation['self_designation_id']) && !isset($selfDesignations[$rawSelfDesignation['self_designation_id']])) {
                $selfDesignations[$rawSelfDesignation['self_designation_id']] = new SelfDesignation(
                    $rawSelfDesignation['self_designation_id'],
                    $rawSelfDesignation['name']
                );
            }
        }

        return $selfDesignations;
    }

    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'self_designation_id');
        $selfDesignations = $this->get($ids);

        // Sort by name
        usort($selfDesignations, function ($a, $b) {
            return strcmp(GreekNormalizer::normalize($a->getName()), GreekNormalizer::normalize($b->getName()));
        });

        return $selfDesignations;
    }

    /**
     * Get all self designations with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'self_designations',
            ['self_designations'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getAll());
            }
        );
    }

    /**
     * Get all self designations with all information
     * @return array
     */
    public function getAllJson(): array
    {
        return ArrayToJson::arrayToJson($this->getAll());
    }

    /**
     * Add a new self designation
     * @param  stdClass $data
     * @return SelfDesignation
     * @throws Exception
     */
    public function add(stdClass $data): SelfDesignation
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

            $this->cache->invalidateTags(['self_designations']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing self designation
     * @param  int $id
     * @param  stdClass $data
     * @return SelfDesignation
     * @throws Exception
     */
    public function update(int $id, stdClass $data): SelfDesignation
    {
        $this->dbs->beginTransaction();
        try {
            $selfDesignations = $this->get([$id]);
            if (count($selfDesignations) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Self designation with id ' . $id .' not found.');
            }
            $old = $selfDesignations[$id];

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

            $this->cache->invalidateTags(['self_designations']);

            // update Elastic persons
            $this->container->get('person_manager')->updateElasticSelfDesignation(
                $this->container->get('person_manager')->getSelfDesignationDependencies($id, 'getId')
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
     * Merge two self designations
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return SelfDesignation
     */
    public function merge(int $primaryId, int $secondaryId): SelfDesignation
    {
        if ($primaryId == $secondaryId) {
            throw new BadRequestHttpException(
                'Self designations with id ' . $primaryId .' and id ' . $secondaryId . ' are identical and cannot be merged.'
            );
        }
        $selfDesignations = $this->get([$primaryId, $secondaryId]);
        if (count($selfDesignations) != 2) {
            throw new NotFoundHttpException('Self designation with id ' . $primaryId . 'or' . $secondaryId .' not found.');
        }
        $primary = $selfDesignations[$primaryId];

        $persons = $this->container->get('person_manager')->getSelfDesignationDependencies($secondaryId, 'getMini');

        $this->dbs->beginTransaction();
        try {
            if (!empty($persons)) {
                foreach ($persons as $person) {
                    $selfDesignations = $person->getSelfDesignations();

                    $newIds = [];
                    foreach ($selfDesignations as $selfDesignation) {
                        $id = $selfDesignation->getId();
                        if ($id == $secondaryId) {
                            $id = $primaryId;
                        }
                        // prevent duplicate entries
                        if (!in_array($id, $newIds)) {
                            $newIds[] = $id;
                        }
                    }

                    $this->container->get('person_manager')->update(
                        $person->getId(),
                        json_decode(
                            json_encode(
                                [
                                    'selfDesignations' => array_map(
                                        function ($id) {
                                            return ['id' => $id];
                                        },
                                        $newIds
                                    ),
                                ]
                            )
                        )
                    );
                }
            }

            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            throw $e;
        }

        return $primary;
    }

    /**
     * Delete a self designation
     * @param int $id
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $selfDesignations = $this->get([$id]);
            if (count($selfDesignations) == 0) {
                throw new NotFoundHttpException('Self designation with id ' . $id .' not found.');
            }
            $old = $selfDesignations[$id];

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            $this->cache->invalidateTags(['self_designations']);

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
