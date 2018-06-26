<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Region;
use AppBundle\Model\RegionWithParents;

class RegionManager extends ObjectManager
{
    public function getRegionsWithParentsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'region_with_parents');
        if (empty($ids)) {
            return $cached;
        }

        $regionsWithParents = [];
        $rawRegionsWithParents = $this->dbs->getRegionsWithParentsByIds($ids);

        foreach ($rawRegionsWithParents as $rawRegionWithParents) {
            $ids = explode(':', $rawRegionWithParents['ids']);
            $names = explode(':', $rawRegionWithParents['names']);
            $historicalNames = explode(':', $rawRegionWithParents['historical_names']);
            $isCities = explode(':', $rawRegionWithParents['is_cities']);
            $pleiadesIds = explode(':', $rawRegionWithParents['pleiades_ids']);

            $regions = [];
            foreach (array_keys($ids) as $key) {
                $regions[] = new Region(
                    (int)$ids[$key],
                    $names[$key],
                    $historicalNames[$key],
                    $isCities[$key] === 'true',
                    $pleiadesIds[$key] == '' ? null : (int)$pleiadesIds[$key]
                );
            }
            $regionWithParents = new RegionWithParents($regions);

            foreach ($ids as $id) {
                $regionWithParents->addCacheDependency('region.' . $id);
            }

            $regionsWithParents[$regionWithParents->getId()] = $regionWithParents;
        }

        $this->setCache($regionsWithParents, 'region_with_parents');
        return $cached + $regionsWithParents;
    }

    public function getAllRegionsWithParents(): array
    {
        $cache = $this->cache->getItem('regions');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'region_id');
        $regionsWithParents = $this->getRegionsWithParentsByIds($ids);

        // Sort by name
        usort($regionsWithParents, function ($a, $b) {
            return strcmp($a->getNameHistoricalName(), $b->getNameHistoricalName());
        });

        $cache->tag(['regions']);
        $this->cache->save($cache->set($regionsWithParents));
        return $regionsWithParents;
    }

    public function getRegionsWithParentsByRegion(int $regionId): array
    {
        $rawIds = $this->dbs->getRegionsByRegion($regionId);
        $ids = self::getUniqueIds($rawIds, 'region_id');
        $regionsWithParents = $this->getRegionsWithParentsByIds($ids);

        // Sort by name
        usort($regionsWithParents, function ($a, $b) {
            return strcmp($a->getNameHistoricalName(), $b->getNameHistoricalName());
        });

        return $regionsWithParents;
    }

    public function addRegionWithParents(stdClass $data): RegionWithParents
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
                && !(
                    property_exists($data, 'parent')
                    && !(
                        $data->parent == null
                        || (property_exists($data->parent, 'id') && is_numeric($data->parent->id))
                    )
                )
                && !(
                    property_exists($data, 'individualHistoricalName')
                    && !($data->individualHistoricalName == null || is_string($data->individualHistoricalName))
                )
                && !(
                    property_exists($data, 'pleiades')
                    && !($data->pleiades == null || is_numeric($data->pleiades))
                )
                && !(
                    property_exists($data, 'isCity')
                    && !($data->isCity == null || is_bool($data->isCity))
                )
            ) {
                $regionId = $this->dbs->insert(
                    (property_exists($data, 'parent') && $data->parent != null) ? $data->parent->id : null,
                    $data->individualName,
                    property_exists($data, 'individualHistoricalName') ? $data->individualHistoricalName : null,
                    (property_exists($data, 'isCity') && is_bool($data->isCity)) ? $data->isCity : false
                );
                if (property_exists($data, 'pleiades') && is_numeric($data->pleiades)) {
                    $this->dbs->upsertPleiades($regionId, $data->pleiades);
                }
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new region data
            $newRegionWithParents = $this->getRegionsWithParentsByIds([$regionId])[$regionId];

            $this->updateModified(null, $newRegionWithParents);

            // update cache
            $this->cache->invalidateTags(['regions']);
            $this->setCache([$newRegionWithParents->getId() => $newRegionWithParents], 'region_with_parents');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegionWithParents;
    }

    public function updateRegionWithParents(int $regionId, stdClass $data): RegionWithParents
    {
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getRegionsWithParentsByIds([$regionId]);
            if (count($regionsWithParents) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Region with id ' . $regionId .' not found.');
            }
            $regionWithParents = $regionsWithParents[$regionId];

            // update region data
            $correct = false;
            if (property_exists($data, 'parent')
                && $data->parent == null
            ) {
                $correct = true;
                $this->dbs->updateParent($regionId, null);
            }
            if (property_exists($data, 'parent')
                && $data->parent != null
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
                && $data->parent->id != $regionId
            ) {
                $correct = true;
                $this->dbs->updateParent($regionId, $data->parent->id);
            }
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
            ) {
                $correct = true;
                $this->dbs->updateName($regionId, $data->individualName);
            }
            if (property_exists($data, 'individualHistoricalName')
                && is_string($data->individualHistoricalName)
            ) {
                $correct = true;
                $this->dbs->updateHistoricalName($regionId, $data->individualHistoricalName);
            }
            if (property_exists($data, 'pleiades')
                && is_numeric($data->pleiades)
            ) {
                $correct = true;
                $this->dbs->upsertPleiades($regionId, $data->pleiades);
            }
            if (property_exists($data, 'isCity')
                && is_bool($data->isCity)
            ) {
                $correct = true;
                $this->dbs->updateIsCity($regionId, $data->isCity);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new region data
            $this->cache->invalidateTags(['region_with_parents.' . $regionId, 'region.' . $regionId, 'regions']);
            $this->cache->deleteItem('region_with_parents.' . $regionId);
            $this->cache->deleteItem('region.' . $regionId);
            $newRegionWithParents = $this->getRegionsWithParentsByIds([$regionId])[$regionId];

            $this->updateModified($regionWithParents, $newRegionWithParents);

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getManuscriptsDependenciesByRegion($regionId);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newRegionWithParents;
    }

    public function mergeRegionsWithParents(int $primaryId, int $secondaryId): RegionWithParents
    {
        $regionsWithParents = $this->getRegionsWithParentsByIds([$primaryId, $secondaryId]);
        if (count($regionsWithParents) != 2) {
            if (!array_key_exists($primaryId, $regionsWithParents)) {
                throw new NotFoundHttpException('Region with id ' . $primaryId .' not found.');
            }
            if (!array_key_exists($secondaryId, $regionsWithParents)) {
                throw new NotFoundHttpException('Region with id ' . $secondaryId .' not found.');
            }
            throw new BadRequestHttpException(
                'Regions with id ' . $primaryId .' and id ' . $secondaryId . ' cannot be merged.'
            );
        }
        list($primary, $secondary) = array_values($regionsWithParents);
        $updates = [];
        if (empty($primary->getIndividualName()) && !empty($secondary->getIndividualName())) {
            $updates['individualName'] = $secondary->getIndividualName();
        }
        if (empty($primary->getIndividualHistoricalName()) && !empty($secondary->getIndividualHistoricalName())) {
            $updates['individualHistoricalName'] = $secondary->getIndividualHistoricalName();
        }
        if (empty($primary->getPleiades()) && !empty($secondary->getPleiades())) {
            $updates['pleiades'] = $secondary->getPleiades();
        }

        $manuscripts = $this->container->get('manuscript_manager')->getManuscriptsDependenciesByRegion($secondaryId, true);
        // Only keep dependencies based on origin
        // Locations of the manuscripts themselves never are regions
        $manuscripts = array_filter($manuscripts, function ($manuscript) use ($secondaryId) {
            if (!empty($manuscript->getOrigin())
                && $manuscript->getOrigin()->getId() == $this->container->get('location_manager')->getLocationByRegion($secondaryId)
            ) {
                return true;
            }
            return false;
        });
        $institutions = $this->container->get('institution_manager')->getInstitutionsByRegion($secondaryId);
        $regions = $this->getRegionsWithParentsByRegion($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($updates)) {
                $primary = $this->updateRegionWithParents($primaryId, json_decode(json_encode($updates)));
            }
            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $this->container->get('manuscript_manager')->updateManuscript(
                        $manuscript->getId(),
                        json_decode(json_encode([
                            'origin' => [
                                'id' => $this->container->get('location_manager')->getLocationByRegion($primaryId)
                            ]
                        ]))
                    );
                }
            }
            if (!empty($institutions)) {
                foreach ($institutions as $institution) {
                    $this->container->get('institution_manager')->updateInstitution(
                        $institution->getId(),
                        json_decode(json_encode(['regionWithParents' => ['id' => $primaryId]]))
                    );
                }
            }
            if (!empty($regions)) {
                foreach ($regions as $region) {
                    $this->updateRegionWithParents(
                        $region->getId(),
                        json_decode(json_encode(['parent' => ['id' => $primaryId]]))
                    );
                }
            }
            $this->delRegion($secondaryId);

            $this->cache->invalidateTags(['regions']);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            // TODO: invalidate caches and revert elasticsearch updates when rolling back, because part of them can be updated
            throw $e;
        }

        return $primary;
    }

    public function delRegion(int $regionId): void
    {
        $this->dbs->beginTransaction();
        try {
            $regionsWithParents = $this->getRegionsWithParentsByIds([$regionId]);
            if (count($regionsWithParents) == 0) {
                throw new NotFoundHttpException('Region with id ' . $regionId .' not found.');
            }
            $regionWithParents = $regionsWithParents[$regionId];

            $this->dbs->delete($regionId);

            // empty cache
            $this->cache->invalidateTags(['region_with_parents.' . $regionId, 'regions']);
            $this->cache->deleteItem('region_with_parents.' . $regionId);

            $this->updateModified($regionWithParents, null);

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
