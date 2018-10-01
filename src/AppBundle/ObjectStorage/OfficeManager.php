<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Office;
use AppBundle\Model\OfficeWithParents;
use AppBundle\Utils\ArrayToJson;

/**
 * ObjectManager for offices
 * Servicename: office_manager
 */
class OfficeManager extends ObjectManager
{
    /**
     * Get single offices with all information
     * @param  array $ids
     * @return array
     */
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

    /**
     * Get single offices with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data)
    {
        return $this->wrapDataCache(
            Office::CACHENAME,
            $data,
            'office_id',
            function ($data) {
                $offices = [];

                $regionIds = self::getUniqueIds($data, 'region_id');
                $regionIds = array_filter($regionIds, function ($regionId) {
                    return $regionId != null;
                });
                $regionsWithParents = $this->container->get('region_manager')->getWithParents($regionIds);

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
        );
    }

    /**
     * Get offices with parents with all information
     * @param  array $ids
     * @return array
     */
    public function getWithParents(array $ids)
    {
        return $this->wrapCache(
            OfficeWithParents::CACHENAME,
            $ids,
            function ($ids) {
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
        );
    }

    /**
     * Get all offices with parents with all information
     * @return array
     */
    public function getAll(): array
    {
        return $this->wrapArrayCache(
            'offices_with_parents',
            ['offices'],
            function () {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, 'office_id');
                $officesWithParents = $this->getWithParents($ids);

                // Sort by name
                usort($officesWithParents, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $officesWithParents;
            }
        );
    }

    /**
     * Get all offices that are dependent on a specific office
     * @param  int   $officeId
     * @return array
     */
    public function getOfficeDependencies(int $officeId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOfficeId($regionId), 'getWithParents');
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
     * Clear cache
     * @param array $ids
     */
    public function reset(array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteCache(Office::CACHENAME, $id);
            $this->deleteCache(OfficeWithParents::CACHENAME, $id);
        }

        $this->getWithParents($ids);
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

            // update cache
            $this->cache->invalidateTags(['offices']);

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
            $this->deleteCache(Office::CACHENAME, $id);
            $this->deleteCache(OfficeWithParents::CACHENAME, $id);
            $new = $this->getWithParents([$id])[$id];

            $this->updateModified($old, $new);

            // update Elastic persons
            $persons = $this->container->get('person_manager')->getOfficeDependenciesWithChildren($id, true);
            $this->container->get('person_manager')->elasticIndex($persons);

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
        list($primary, $secondary) = array_values($officesWithParents);

        $persons = $this->container->get('person_manager')->getOfficeDependencies($secondaryId, true);
        $offices = $this->getOfficesWithParentsByOffice($secondaryId);

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
                    $this->container->get('person_manager')->update(
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

            // Reset caches and elasticsearch
            $this->reset([$primaryId]);
            $this->cache->invalidateTags(['offices']);
            if (!empty($persons)) {
                $this->container->get('person_manager')->reset(self::getIds($persons));
            }
            if (!empty($offices)) {
                $this->reset(self::getIds($offices));
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

            // clear cache
            $this->deleteCache(Office::CACHENAME, $id);
            $this->deleteCache(OfficeWithParents::CACHENAME, $id);
            $this->cache->invalidateTags(['offices']);

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
