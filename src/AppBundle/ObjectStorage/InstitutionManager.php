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
    public function get(array $ids): array
    {
        $institutions = [];
        $rawInstitutions = $this->dbs->getInstitutionsByIds($ids);

        foreach ($rawInstitutions as $rawInstitution) {
            $institutions[$rawInstitution['institution_id']] = new Institution(
                $rawInstitution['institution_id'],
                $rawInstitution['name']
            );
        }

        return $institutions;
    }

    public function getWithData(array $data): array
    {
        $institutions = [];
        foreach ($data as $rawInstitution) {
            if (isset($rawInstitution['institution_id'])
                && !isset($institutions[$rawInstitution['institution_id']])
            ) {
                $institutions[$rawInstitution['institution_id']] = new Institution(
                    $rawInstitution['institution_id'],
                    $rawInstitution['institution_name']
                );
            }
        }

        return $institutions;
    }

    public function getInstitutionsByRegion(int $regionId): array
    {
        $rawInstitutions = $this->dbs->getInstitutionsByRegion($regionId);
        $institutionIds = self::getUniqueIds($rawInstitutions, 'institution_id');
        return $this->get($institutionIds);
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
            $newInstitution = $this->get([$institutionId])[$institutionId];

            $this->updateModified(null, $newInstitution);

            $this->cache->invalidateTags(['institutions']);

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
            $institutions = $this->get([$institutionId]);
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
            $newInstitution = $this->get([$institutionId])[$institutionId];

            $this->updateModified($institution, $newInstitution);

            $this->cache->invalidateTags(['institutions']);

            // update Elastic manuscripts
            $manuscriptIds = $this->container->get('manuscript_manager')->getInstitutionDependencies($institutionId, 'getId');
            $this->container->get('manuscript_manager')->updateElasticByIds($manuscriptIds);

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
            $institutions = $this->get([$institutionId]);
            if (count($institutions) == 0) {
                throw new NotFoundHttpException('Institution with id ' . $institutionId .' not found.');
            }
            $institution = $institutions[$institutionId];

            $this->dbs->delete($institutionId);

            $this->updateModified($institution, null);

            $this->cache->invalidateTags(['institutions']);

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
