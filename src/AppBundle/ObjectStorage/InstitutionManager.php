<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Institution;

class InstitutionManager extends ObjectManager
{
    public function getInstitutionsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'institution');
        if (empty($ids)) {
            return $cached;
        }

        $institutions = [];
        $rawInstitutions = $this->dbs->getInstitutionsByIds($ids);

        foreach ($rawInstitutions as $rawInstitution) {
            $institutions[$rawInstitution['institution_id']] = new Institution(
                $rawInstitution['institution_id'],
                $rawInstitution['name']
            );
        }

        $this->setCache($institutions, 'institution');

        return $cached + $institutions;
    }

    public function getInstitutionsByRegion(int $regionId): array
    {
        $rawInstitutions = $this->dbs->getInstitutionsByRegion($regionId);
        $institutionIds = self::getUniqueIds($rawInstitutions, 'institution_id');
        return $this->getInstitutionsByIds($institutionIds);
    }

    /**
     * Clear cache and update elasticsearch
     * @param array $ids institution ids
     */
    public function resetInstitutions(array $ids): void
    {
        foreach ($ids as $id) {
            $this->cache->deleteItem('institution.' . $id);
        }

        $this->getInstitutionsByIds($ids);

        $this->cache->invalidateTags(['institutions']);
    }

    public function addInstitution(stdClass $data, bool $library = false, bool $monastery = false): Institution
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'regionWithParents')
                && property_exists($data->regionWithParents, 'id')
                && is_numeric($data->regionWithParents->id)
            ) {
                $institutionId = $this->dbs->insert($data->name, $data->regionWithParents->id, $library, $monastery);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new institution data
            $this->cache->invalidateTags(['institutions']);
            $newInstitution = $this->getInstitutionsByIds([$institutionId])[$institutionId];

            $this->updateModified(null, $newInstitution);

            // update cache
            $this->cache->invalidateTags(['institutions']);
            $this->setCache([$newInstitution->getId() => $newInstitution], 'institution');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newInstitution;
    }

    public function updateInstitution(int $institutionId, stdClass $data): Institution
    {
        $this->dbs->beginTransaction();
        try {
            $institutions = $this->getInstitutionsByIds([$institutionId]);
            if (count($institutions) == 0) {
                throw new NotFoundHttpException('Institution with id ' . $institutionId .' not found.');
            }
            $institution = $institutions[$institutionId];

            // update institution data
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($institutionId, $data->name);
            }
            if (property_exists($data, 'regionWithParents')
                && property_exists($data->regionWithParents, 'id')
                && is_numeric($data->regionWithParents->id)
            ) {
                $correct = true;
                $this->dbs->updateRegion($institutionId, $data->regionWithParents->id);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new institution data
            $this->cache->invalidateTags(['institution.' . $institutionId, 'institutions']);
            $this->cache->deleteItem('institution.' . $institutionId);
            $newInstitution = $this->getInstitutionsByIds([$institutionId])[$institutionId];

            // TODO: make sure parent edits are logged
            $this->updateModified($institution, $newInstitution);

            // update cache
            $this->setCache([$newInstitution->getId() => $newInstitution], 'institution');

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getInstitutionDependencies($institutionId);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newInstitution;
    }

    public function delInstitution(int $institutionId): void
    {
        $this->dbs->beginTransaction();
        try {
            $institutions = $this->getInstitutionsByIds([$institutionId]);
            if (count($institutions) == 0) {
                throw new NotFoundHttpException('Institution with id ' . $institutionId .' not found.');
            }
            $institution = $institutions[$institutionId];

            $this->dbs->delete($institutionId);

            // clear cache
            $this->cache->invalidateTags(['institution.' . $institutionId, 'institutions']);
            $this->cache->deleteItem('institution.' . $institutionId);

            $this->updateModified($institution, null);

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
