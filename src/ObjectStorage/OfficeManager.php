<?php

namespace App\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Exceptions\DependencyException;
use App\Model\Office;
use App\Model\OfficeWithParents;
use App\Utils\ArrayToJson;

/**
 * ObjectManager for offices
 */
class OfficeManager extends ObjectManager
{
    /**
     * Get single offices with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $rawOffices = $this->dbs->getOfficesByIds($ids);
        return $this->getWithData($rawOffices);
    }

    /**
     * Get single offices with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        $offices = [];

        $regionIds = self::getUniqueIds($data, 'region_id');
        $regionIds = array_filter($regionIds, function ($regionId) {
            return $regionId != null;
        });
        $regionsWithParents = $this->container->get(RegionManager::class)->getWithParents($regionIds);

        foreach ($data as $rawOffice) {
            if (isset($rawOffice['office_id']) && !isset($offices[$rawOffice['office_id']])) {
                $offices[$rawOffice['office_id']] = new Office(
                    $rawOffice['office_id'],
                    $rawOffice['name'] !== '' ? $rawOffice['name'] : null,
                    $rawOffice['region_id'] ? $regionsWithParents[$rawOffice['region_id']] : null
                );
            }
        }

        return $offices;
    }

    /**
     * Get offices with parents with all information
     * @param  array $ids
     * @return array
     */
    public function getWithParents(array $ids): array
    {
        $officesWithParents = [];
        $rawOfficesWithParents = $this->dbs->getOfficesWithParentsByIds($ids);

        foreach ($rawOfficesWithParents as $rawOfficeWithParents) {
            $ids = json_decode($rawOfficeWithParents['ids']);
            $names = json_decode($rawOfficeWithParents['names']);
            $regionIds = json_decode($rawOfficeWithParents['regions']);

            $rawOffices = [];
            foreach (array_keys($ids) as $key) {
                $rawOffices[] = [
                    'office_id' => (int)$ids[$key],
                    'name' => $names[$key],
                    'region_id' => $regionIds[$key],
                ];
            }

            $offices = $this->getWithData($rawOffices);

            $orderedOffices = [];
            foreach ($ids as $id) {
                $orderedOffices[] = $offices[(int)$id];
            }

            $officeWithParents = new OfficeWithParents($orderedOffices);

            $officesWithParents[$officeWithParents->getId()] = $officeWithParents;
        }

        return $officesWithParents;
    }

    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'office_id');
        $officesWithParents = $this->getWithParents($ids);

        // Sort by name
        usort($officesWithParents, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $officesWithParents;
    }

    /**
     * Get all offices with parents with all information
     * @return array
     */
    public function getAllJson(): array
    {
        return ArrayToJson::arrayToJson($this->getAll());
    }

    /**
     * Get all offices that are dependent on a specific office
     * @param  int   $officeId
     * @return array
     */
    public function getOfficeDependencies(int $officeId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOfficeId($officeId), 'getWithParents');
    }

    /**
     * Get all offices that are dependent on a specific region
     * @param  int   $regionId
     * @return array
     */
    public function getRegionDependencies(int $regionId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionId($regionId), 'getWithParents');
    }

    /**
     * Get all offices that are dependent on a specific region or one of its children
     * @param  int   $regionId
     * @return array
     */
    public function getRegionDependenciesWithChildren(int $regionId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionIdWithChildren($regionId), 'getWithParents');
    }

    /**
     * Add a new office
     * @param  stdClass $data
     * @return OfficeWithParents
     */
    public function add(stdClass $data): OfficeWithParents
    {
        $this->dbs->beginTransaction();
        try {
            if (// Exactly one of name or region is required
                (
                    (
                        property_exists($data, 'individualName')
                        && is_string($data->individualName)
                        && $data->individualName !== ''
                    )
                    xor (
                        property_exists($data, 'individualRegionWithParents')
                        && is_object($data->individualRegionWithParents)
                        && property_exists($data->individualRegionWithParents, 'id')
                        && is_numeric($data->individualRegionWithParents->id)
                    )
                )
                && (
                    !property_exists($data, 'parent')
                    || (
                        $data->parent == null
                        || (
                            is_object($data->parent)
                            && property_exists($data->parent, 'id')
                            && is_numeric($data->parent->id)
                        )
                    )
                )
            ) {
                $id = $this->dbs->insert(
                    (property_exists($data, 'parent') && $data->parent != null)
                        ? $data->parent->id
                        : null,
                    (property_exists($data, 'individualName') && $data->individualName != null)
                        ? $data->individualName
                        : '',
                    (
                        property_exists($data, 'individualRegionWithParents')
                        && $data->individualRegionWithParents != null
                    )
                        ? $data->individualRegionWithParents->id
                        : null
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new office data
            $new = $this->getWithParents([$id])[$id];

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
     * Update an existing office
     * @param  int      $id
     * @param  stdClass $data
     * @return OfficeWithParents
     */
    public function update(int $id, stdClass $data): OfficeWithParents
    {
        $this->dbs->beginTransaction();
        try {
            $officesWithParents = $this->getWithParents([$id]);
            if (count($officesWithParents) == 0) {
                throw new NotFoundHttpException('Office with id ' . $id .' not found.');
            }
            $old = $officesWithParents[$id];

            // update office data
            $correct = false;
            if (property_exists($data, 'parent')
                && $data->parent == null
            ) {
                $correct = true;
                $this->dbs->updateParent($id, null);
            }
            if (property_exists($data, 'parent')
                && $data->parent != null
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
                // Prevent cycles
                && $data->parent->id != $id
                // Prevent cycles
                && !in_array($data->parent->id, self::getUniqueIds($this->dbs->getChildIds($id), 'child_id'))
            ) {
                $correct = true;
                $this->dbs->updateParent($id, $data->parent->id);
            }
            if (// New name, no region
                (
                    property_exists($data, 'individualName')
                    && is_string($data->individualName)
                    && $data->individualName !== ''
                )
                && (
                    (
                        !property_exists($data, 'individualRegionWithParents')
                        && $old->getIndividualRegionWithParents() == null
                    )
                    || (
                        property_exists($data, 'individualRegionWithParents')
                        && $data->individualRegionWithParents == null
                    )
                )
            ) {
                $correct = true;
                $this->dbs->updateName($id, $data->individualName);
                if (property_exists($data, 'individualRegionWithParents')
                    && $data->individualRegionWithParents == null
                ) {
                    $this->dbs->updateRegion($id, null);
                }
            }
            if (// New region, no name
                (
                    property_exists($data, 'individualRegionWithParents')
                    && is_object($data->individualRegionWithParents)
                    && property_exists($data->individualRegionWithParents, 'id')
                    && is_numeric($data->individualRegionWithParents->id)
                )
                && (
                    (
                        !property_exists($data, 'individualName')
                        && ($old->getIndividualName() == null || $old->getIndividualName() === '')
                    )
                    || (
                        property_exists($data, 'individualName')
                        && ($data->individualName == null || $data->individualName === '')
                    )
                )
            ) {
                $correct = true;
                $this->dbs->updateRegion($id, $data->individualRegionWithParents->id);
                if (property_exists($data, 'individualName')
                    && ($data->individualName == null || $data->individualName === '')
                ) {
                    $this->dbs->updateName($id, '');
                }
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new office data
            $new = $this->getWithParents([$id])[$id];

            $this->updateModified($old, $new);

            // update Elastic persons
            $personIds = $this->container->get(PersonManager::class)->getOfficeDependenciesWithChildren($id, 'getId');
            $this->container->get(PersonManager::class)->updateElasticByIds($personIds);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Merge two offices
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return OfficeWithParents
     */
    public function merge(int $primaryId, int $secondaryId): OfficeWithParents
    {
        $officesWithParents = $this->getWithParents([$primaryId, $secondaryId]);
        if (count($officesWithParents) != 2) {
            if (!array_key_exists($primaryId, $officesWithParents)) {
                throw new NotFoundHttpException('Office with id ' . $primaryId .' not found.');
            }
            if (!array_key_exists($secondaryId, $officesWithParents)) {
                throw new NotFoundHttpException('Office with id ' . $secondaryId .' not found.');
            }
            throw new BadRequestHttpException(
                'Offices with id ' . $primaryId .' and id ' . $secondaryId . ' cannot be merged.'
            );
        }
        $primary = $officesWithParents[$primaryId];

        $persons = $this->container->get(PersonManager::class)->getOfficeDependencies($secondaryId, 'getShort');
        $offices = $this->getOfficeDependencies($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($persons)) {
                foreach ($persons as $person) {
                    $officeArray = ArrayToJson::arrayToShortJson($person->getOfficesWithParents());
                    $officeArray = array_values(
                        array_filter($officeArray, function ($officeItem) use ($secondaryId, $primaryId) {
                            return $officeItem['id'] !== $secondaryId && $officeItem['id'] !== $primaryId;
                        })
                    );
                    $officeArray[] = ['id' => $primaryId];
                    $this->container->get(PersonManager::class)->update(
                        $person->getId(),
                        json_decode(json_encode(['offices' => $officeArray]))
                    );
                }
            }
            if (!empty($offices)) {
                foreach ($offices as $office) {
                    $this->update(
                        $office->getId(),
                        json_decode(json_encode(['parent' => ['id' => $primaryId]]))
                    );
                }
            }
            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if (!empty($persons)) {
                $this->container->get(PersonManager::class)->updateElasticByIds(array_keys($persons));
            }

            throw $e;
        }

        return $primary;
    }

    /**
     * Delete an office
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $offices = $this->getWithParents([$id]);
            if (count($offices) == 0) {
                throw new NotFoundHttpException('Office with id ' . $id .' not found.');
            }
            $old = $offices[$id];

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
