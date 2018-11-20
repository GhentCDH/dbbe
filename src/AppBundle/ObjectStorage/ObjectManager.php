<?php

namespace AppBundle\ObjectStorage;

use Exception;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use AppBundle\Model\IdJsonInterface;
use AppBundle\Service\DatabaseService\DatabaseServiceInterface;
use AppBundle\Service\ElasticSearchService\ElasticSearchServiceInterface;

class ObjectManager
{
    protected $dbs;
    protected $cache;
    protected $container;
    protected $ess;
    protected $ts;
    /**
     * @var string
     */
    protected $entityType;

    public function __construct(
        DatabaseServiceInterface $databaseService = null,
        CacheItemPoolInterface $cacheItemPool,
        ContainerInterface $container,
        ElasticSearchServiceInterface $elasticSearchService = null,
        TokenStorageInterface $tokenStorage = null,
        string $entityType = null
    ) {
        $this->dbs = $databaseService;
        $this->cache = new TagAwareAdapter($cacheItemPool);
        $this->container = $container;
        $this->ess = $elasticSearchService;
        $this->ts = $tokenStorage;
        $this->entityType = $entityType;
    }

    protected function getDependencies(array $rawIds, string $method): array
    {
        if ($method == 'getId') {
            return self::getUniqueIds($rawIds, $this->entityType . '_id');
        }
        return $this->{$method}(self::getUniqueIds($rawIds, $this->entityType . '_id'));
    }

    protected function setArrayCache(array $items, string $cacheKey, array $tags): void
    {
        $cache = $this->cache->getItem($cacheKey);
        $cache->tag($tags);
        $this->cache->save($cache->set($items));
    }

    protected function getArrayCache(string $cacheKey)
    {
        $cache = $this->cache->getItem($cacheKey);
        if ($cache->isHit()) {
            return $cache->get();
        }
        return null;
    }

    protected function wrapArrayCache(string $cacheKey, array $tags, callable $function): array
    {
        $cache = $this->getArrayCache($cacheKey);
        if (!is_null($cache)) {
            return $cache;
        }
        $result = $function();
        $this->setArrayCache($result, $cacheKey, $tags);
        return $result;
    }

    protected function wrapArrayTypeCache(string $cacheKey, string $type, array $tags, callable $function): array
    {
        $cache = $this->getArrayCache($cacheKey . '.' . $type);
        if (!is_null($cache)) {
            return $cache;
        }
        $result = $function($type);
        $this->setArrayCache($result, $cacheKey . '.' . $type, $tags);
        return $result;
    }


    /**
     * (Re-)index elasticsearch
     * @param  array  $ids
     */
    public function updateElasticByIds(array $ids): void
    {
        $shorts = $this->getShort($ids);
        $delIds = array_diff($ids, array_keys($shorts));
        if (count($shorts) > 0) {
            $this->ess->addMultiple($shorts);
        }
        if (count($delIds) > 0) {
            $this->ess->deleteMultiple($delIds);
        }
    }

    /**
     * Update entity modified date and create a revision
     * @param IdJsonInterface|null $old Old values, null in case of an inserted object
     * @param IdJsonInterface|null $new New values, null in case of a deleted object
     */
    protected function updateModified(IdJsonInterface $old = null, IdJsonInterface $new = null): void
    {
        if ($old == null && $new == null) {
            throw new Exception('The old and new value cannot both be null.');
        }
        if ($old == null && $new != null) {
            $this->dbs->updateModified($new->getId());
        }
        $this->dbs->createRevision(
            $old == null ? get_class($new) : get_class($old),
            $old == null ? $new->getId() : $old->getId(),
            $this->ts->getToken()->getUser()->getId(),
            $old == null ? null : json_encode($old->getJson()),
            $new == null ? null : json_encode($new->getJson())
        );
    }

    protected static function getUniqueIds(array $rows, string $key, string $filterKey = null, $filterValue = null): array
    {
        $uniqueIds = [];
        foreach ($rows as $row) {
            if (isset($filterKey) && $row[$filterKey] !== $filterValue) {
                continue;
            }
            // array_to_json(array_agg())
            if (is_array(json_decode($row[$key]))) {
                foreach (json_decode($row[$key]) as $id) {
                    if ($id != null && !in_array($id, $uniqueIds)) {
                        $uniqueIds[] = $id;
                    }
                }
            } else {
                // integer
                if ($row[$key] != null && !in_array($row[$key], $uniqueIds)) {
                    $uniqueIds[] = $row[$key];
                }
            }
        }
        return $uniqueIds;
    }

    protected static function getIds(array $entities): array
    {
        $ids = [];
        foreach ($entities as $entity) {
            $ids[] = $entity->getId();
        }
        return $ids;
    }

    protected static function calcDiff(array $newJsonArray, array $oldObjectArray): array
    {
        $newIds = array_unique(array_map(
            function ($newJsonItem) {
                return $newJsonItem->id;
            },
            $newJsonArray
        ));
        $oldIds = array_unique(array_map(
            function ($oldObjectItem) {
                return $oldObjectItem->getId();
            },
            $oldObjectArray
        ));

        $delIds = array_diff($oldIds, $newIds);
        $addIds = array_diff($newIds, $oldIds);

        return [$delIds, $addIds];
    }
}
