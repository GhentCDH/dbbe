<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use AppBundle\Exceptions\DependencyException;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Occupation;

class OccupationManager extends ObjectManager
{
    public function getOccupationsByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'occupation');
        if (empty($ids)) {
            return $cached;
        }

        $occupations = [];
        $rawOccupations = $this->dbs->getOccupationsByIds($ids);

        foreach ($rawOccupations as $rawOccupation) {
            $occupations[$rawOccupation['occupation_id']] = new Occupation(
                $rawOccupation['occupation_id'],
                $rawOccupation['name'],
                $rawOccupation['is_function']
            );
        }

        $this->setCache($occupations, 'occupation');

        return $cached + $occupations;
    }

    public function getAllOccupations(): array
    {
        $cache = $this->cache->getItem('occupations');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $occupations = [];
        $rawOccupations = $this->dbs->getAllOccupations();

        foreach ($rawOccupations as $rawOccupation) {
            $occupations[] = new Occupation(
                $rawOccupation['occupation_id'],
                $rawOccupation['name'],
                $rawOccupation['is_function']
            );
        }

        // Sort by name
        usort($occupations, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['occupations']);
        $this->cache->save($cache->set($occupations));
        return $occupations;
    }

    public function getAllTypes(): array
    {
        return array_filter($this->getAllOccupations(), function ($occupation) {
            return !$occupation->getIsFunction();
        });
    }

    public function getAllFunctions(): array
    {
        return array_filter($this->getAllOccupations(), function ($occupation) {
            return $occupation->getIsFunction();
        });
    }

    public function addOccupation(stdClass $data): Occupation
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'isFunction')
                && is_bool($data->isFunction)
            ) {
                $occupationId = $this->dbs->insert(
                    $data->name,
                    $data->isFunction
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $newOccupation = $this->getOccupationsByIds([$occupationId])[$occupationId];

            $this->updateModified(null, $newOccupation);

            // update cache
            $this->cache->invalidateTags(['occupations']);
            $this->setCache([$newOccupation->getId() => $newOccupation], 'occupation');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOccupation;
    }

    public function updateOccupation(int $occupationId, stdClass $data): Occupation
    {
        $this->dbs->beginTransaction();
        try {
            $occupations = $this->getOccupationsByIds([$occupationId]);
            if (count($occupations) == 0) {
                throw new NotFoundHttpException('Occupation with id ' . $occupationId .' not found.');
            }
            $occupation = $occupations[$occupationId];

            // update occupation data
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($occupationId, $data->name);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new occupation data
            $this->cache->invalidateTags(['occupation.' . $occupationId, 'occupations']);
            $this->cache->deleteItem('occupation.' . $occupationId);
            $newOccupation = $this->getOccupationsByIds([$occupationId])[$occupationId];

            $this->updateModified($occupation, $newOccupation);

            // update cache
            $this->setCache([$newOccupation->getId() => $newOccupation], 'occupation');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOccupation;
    }

    public function delOccupation(int $occupationId): void
    {
        $this->dbs->beginTransaction();
        try {
            $occupations = $this->getOccupationsByIds([$occupationId]);
            if (count($occupations) == 0) {
                throw new NotFoundHttpException('Occupation with id ' . $occupationId .' not found.');
            }
            $occupation = $occupations[$occupationId];

            $this->dbs->delete($occupationId);

            // clear cache
            $this->cache->invalidateTags(['occupation.' . $occupationId, 'occupations']);
            $this->cache->deleteItem('occupation.' . $occupationId);

            $this->updateModified($occupation, null);

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
