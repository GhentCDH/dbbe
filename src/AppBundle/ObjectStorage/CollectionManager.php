<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Collection;

class CollectionManager extends ObjectManager
{
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Collection::CACHENAME,
            $ids,
            function ($ids) {
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
        );
    }

    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            Collection::CACHENAME,
            $data,
            'collection_id',
            function ($data) {
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
        );
    }

    public function addCollection(stdClass $data): Collection
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'institution')
                && property_exists($data->institution, 'id')
                && is_numeric($data->institution->id)
            ) {
                $collectionId = $this->dbs->insert($data->name, $data->institution->id);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new collection data
            $newCollection = $this->get([$collectionId])[$collectionId];

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getCollectionDependencies($collectionId, true);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            $this->updateModified(null, $newCollection);

            // update cache
            $this->cache->invalidateTags(['collections']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newCollection;
    }

    public function updateCollection(int $collectionId, stdClass $data): Collection
    {
        $this->dbs->beginTransaction();
        try {
            $collections = $this->get([$collectionId]);
            if (count($collections) == 0) {
                throw new NotFoundHttpException('Collection with id ' . $collectionId .' not found.');
            }
            $collection = $collections[$collectionId];

            // update collection data
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($collectionId, $data->name);
            }
            if (property_exists($data, 'institution')
                && property_exists($data->institution, 'id')
                && is_numeric($data->institution->id)
            ) {
                $correct = true;
                $this->dbs->updateInstitution($collectionId, $data->institution->id);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new collection data
            $this->deleteCache(Collection::CACHENAME, $collectionId);
            $newCollection = $this->get([$collectionId])[$collectionId];

            $this->updateModified($collection, $newCollection);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newCollection;
    }

    public function delCollection(int $collectionId): void
    {
        $this->dbs->beginTransaction();
        try {
            $collections = $this->get([$collectionId]);
            if (count($collections) == 0) {
                throw new NotFoundHttpException('Collection with id ' . $collectionId .' not found.');
            }
            $collection = $collections[$collectionId];

            $this->dbs->delete($collectionId);

            // clear cache
            $this->deleteCache(Collection::CACHENAME, $collectionId);
            $this->cache->invalidateTags(['collections']);

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
