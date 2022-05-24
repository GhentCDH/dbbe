<?php

namespace App\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Exceptions\DependencyException;
use App\Model\Collection;

class CollectionManager extends ObjectManager
{
    public function get(array $ids): array
    {
        $collections = [];
        $rawCollections = $this->dbs->getCollectionsByIds($ids);

        foreach ($rawCollections as $rawCollection) {
            $collections[$rawCollection['collection_id']] = new Collection(
                $rawCollection['collection_id'],
                $rawCollection['name']
            );
        }

        return $collections;
    }

    public function getWithData(array $data): array
    {
        $collections = [];
        foreach ($data as $rawCollection) {
            if (isset($rawCollection['collection_id'])
                && !isset($collections[$rawCollection['collection_id']])
            ) {
                $collections[$rawCollection['collection_id']] = new Collection(
                    $rawCollection['collection_id'],
                    $rawCollection['collection_name']
                );
            }
        }

        return $collections;
    }

    public function add(stdClass $data): Collection
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'institution')
                && property_exists($data->institution, 'id')
                && is_numeric($data->institution->id)
            ) {
                $id = $this->dbs->insert($data->name, $data->institution->id);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new collection data
            $new = $this->get([$id])[$id];

            // update Elastic manuscripts
            $this->container->get(ManuscriptManager::class)->updateElasticByIds(
                $this->container->get(ManuscriptManager::class)->getCollectionDependencies($id, 'getId')
            );

            $this->updateModified(null, $new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    public function update(int $id, stdClass $data): Collection
    {
        $this->dbs->beginTransaction();
        try {
            $collections = $this->get([$id]);
            if (count($collections) == 0) {
                throw new NotFoundHttpException('Collection with id ' . $id .' not found.');
            }
            $old = $collections[$id];

            // update collection data
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($id, $data->name);
            }
            if (property_exists($data, 'institution')
                && property_exists($data->institution, 'id')
                && is_numeric($data->institution->id)
            ) {
                $correct = true;
                $this->dbs->updateInstitution($id, $data->institution->id);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new collection data
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);


            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    public function delete(int $collectionId): void
    {
        $this->dbs->beginTransaction();
        try {
            $collections = $this->get([$collectionId]);
            if (count($collections) == 0) {
                throw new NotFoundHttpException('Collection with id ' . $collectionId .' not found.');
            }
            $collection = $collections[$collectionId];

            $this->dbs->delete($collectionId);

            $this->updateModified($collection, null);

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
