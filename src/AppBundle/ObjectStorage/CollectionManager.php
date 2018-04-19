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
    public function getCollectionsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'collection');
        if (empty($ids)) {
            return $cached;
        }

        $collections = [];
        $rawCollections = $this->dbs->getCollectionsByIds($ids);

        foreach ($rawCollections as $rawCollection) {
            $collections[$rawCollection['collection_id']] = new Collection(
                $rawCollection['collection_id'],
                $rawCollection['name']
            );
        }

        $this->setCache($collections, 'collection');

        return $cached + $collections;
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
            $this->cache->invalidateTags(['collection.' . $collectionId, 'collections']);
            $this->cache->deleteItem('collection.' . $collectionId);
            $newCollection = $this->getCollectionsByIds([$collectionId])[$collectionId];

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getManuscriptsDependenciesByCollection($collectionId);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            $this->updateModified(null, $newCollection);

            // update cache
            $this->setCache([$newCollection->getId() => $newCollection], 'collection');

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
            $collections = $this->getCollectionsByIds([$collectionId]);
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
            $this->cache->invalidateTags(['collections']);
            $this->cache->deleteItem('collection.' . $collectionId);
            $newCollection = $this->getCollectionsByIds([$collectionId])[$collectionId];

            $this->updateModified($collection, $newCollection);

            // update cache
            $this->setCache([$newCollection->getId() => $newCollection], 'collection');

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
            $collections = $this->getCollectionsByIds([$collectionId]);
            if (count($collections) == 0) {
                throw new NotFoundHttpException('Collection with id ' . $collectionId .' not found.');
            }
            $collection = $collections[$collectionId];

            $this->dbs->delete($collectionId);

            // load new collection data
            $this->cache->invalidateTags(['collection.' . $collectionId, 'collections']);
            $this->cache->deleteItem('collection.' . $collectionId);

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
