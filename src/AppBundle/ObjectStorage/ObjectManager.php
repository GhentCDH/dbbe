<?php

namespace AppBundle\ObjectStorage;

use Exception;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


use AppBundle\Model\CacheObject;
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

    public function __construct(
        DatabaseServiceInterface $databaseService,
        CacheItemPoolInterface $cacheItemPool,
        ContainerInterface $container,
        ElasticSearchServiceInterface $elasticSearchService = null,
        TokenStorageInterface $tokenStorage = null
    ) {
        $this->dbs = $databaseService;
        $this->cache = new TagAwareAdapter($cacheItemPool);
        $this->container = $container;
        $this->ess = $elasticSearchService;
        $this->ts = $tokenStorage;
    }

    protected function getDependencies(array $rawIds, bool $short = false): array
    {
        if ($short) {
            return $this->getShort(self::getUniqueIds($rawIds, $this->entityType . '_id'));
        }
        return $this->getMini(self::getUniqueIds($rawIds, $this->entityType . '_id'));
    }

    protected function setCache(array $items, string $cacheKey): void
    {
        foreach ($items as $id => $item) {
            $cache = $this->cache->getItem($cacheKey . '.' . $id);
            $this->cache->save($cache->set($this->linkCache($item)));
        }
    }

    protected function setArrayCache(array $items, string $cacheKey, array $tags): void
    {
        // var_dump($cacheKey);
        $cache = $this->cache->getItem($cacheKey);
        $cache->tag($tags);
        $this->cache->save($cache->set($this->createCache($items)));
    }

    private function linkCache($item)
    {
        if (is_object($item) && method_exists($item, 'get')) {
            $data = [];
            foreach ($item->get() as $key => $value) {
                if (isset($value) && (!is_array($value) || !empty($value))) {
                    $data[$key] = $this->createCache($value);
                }
            }
            return new CacheObject(
                get_class($item),
                $data
            );
        }
        return $item;
    }

    private function createCache($item)
    {
        if (is_object($item) && method_exists($item, 'getCacheLink')) {
            return $item->getCacheLink();
        }
        if (is_object($item) && method_exists($item, 'get')) {
            return $this->linkCache($item);
        }
        if (is_array($item)) {
            // var_dump($item);
            // var_dump(array_map(
            //     function ($element) {
            //         return $this->createCache($element);
            //     },
            //     $item
            // ));
            return array_map(
                function ($element) {
                    return $this->createCache($element);
                },
                $item
            );
        }

        return $item;
    }

    protected function getCache(array $ids, string $cacheKey): array
    {
        $cached = [];

        foreach ($ids as $key => $id) {
            $cache = $this->cache->getItem($cacheKey . '.' . $id);
            if ($cache->isHit()) {
                $cached[$id] = $this->unlinkCache($cache->get());
                unset($ids[$key]);
            }
        }

        return [$cached, $ids];
    }

    protected function getSingleCache($id, string $cacheKey)
    {
        $cache = $this->cache->getItem($cacheKey . '.' . $id);
        if ($cache->isHit()) {
            return $this->unlinkCache($cache->get());
        }
        return null;
    }

    protected function getArrayCache(string $cacheKey)
    {
        $cache = $this->cache->getItem($cacheKey);
        if ($cache->isHit()) {
            return $this->resolveCache($cache->get());
        }
        return null;
    }

    protected function unlinkCache($item)
    {
        if (is_object($item) && is_a($item, CacheObject::class)) {
            $data = $item->getData();
            foreach ($data as $key => $value) {
                $data[$key] = $this->resolveCache($value);
            }
            return $item->getClassName()::unlinkCache($data);
        }
        return $item;
    }

    private function resolveCache($item)
    {
        if (is_array($item)) {
            foreach ($item as $index => $value) {
                $item[$index] = $this->resolveCache($value);
            }
            return $item;
        }
        if (is_object($item) && is_a($item, CacheObject::class)) {
            return $this->unlinkCache($item);
        }
        if (is_string($item) && preg_match('/^C:([\w_]+)(?::(\w+))?:(\d+)/', $item, $matches)) {
            if (!empty($matches[2])) {
                switch ($matches[2]) {
                    case 'mini':
                        return $this->container->get($matches[1] . '_manager')->getMini([$matches[3]])[$matches[3]];
                        break;
                    case 'short':
                        return $this->container->get($matches[1] . '_manager')->getShort([$matches[3]])[$matches[3]];
                        break;
                    case 'full':
                        return $this->container->get($matches[1] . '_manager')->getFull([$matches[3]]);
                        break;
                }
            }
            switch ($matches[1]) {
                case 'online_source':
                    return $this->container->get('bibliography_manager')->getOnlineSourcesByIds([$matches[3]])[$matches[3]];
                    break;
                case 'article_bibliography':
                    return $this->container->get('bibliography_manager')->getArticleBibliographiesByIds([$matches[3]])[$matches[3]];
                    break;
                case 'book_bibliography':
                    return $this->container->get('bibliography_manager')->getBookBibliographiesByIds([$matches[3]])[$matches[3]];
                    break;
                case 'book_chapter_bibliography':
                    return $this->container->get('bibliography_manager')->getBookChapterBibliographiesByIds([$matches[3]])[$matches[3]];
                    break;
                case 'online_source_bibliography':
                    return $this->container->get('bibliography_manager')->getOnlineSourceBibliographiesByIds([$matches[3]])[$matches[3]];
                    break;
                case 'content_with_parents':
                    return $this->container->get('content_manager')->getWithParents([$matches[3]])[$matches[3]];
                    break;
                case 'region_with_parents':
                    return $this->container->get('region_manager')->getWithParents([$matches[3]])[$matches[3]];
                    break;
                default:
                    return $this->container->get($matches[1] . '_manager')->get([$matches[3]])[$matches[3]];
                    break;
            }
        }
        return $item;
    }

    protected function deleteCache(string $cacheKey, int $id): void
    {
        $this->cache->deleteItem($cacheKey . '.' . $id);
    }

    protected function wrapCache(string $cacheKey, array $ids, callable $function): array
    {
        list($cached, $ids) = $this->getCache($ids, $cacheKey);
        if (empty($ids)) {
            return $cached;
        }

        $objects = $function($ids);

        $this->setCache($objects, $cacheKey);

        return $cached + $objects;
    }

    protected function wrapSingleCache(string $cacheKey, $id, callable $function)
    {
        $cache = $this->getSingleCache($id, $cacheKey);
        if (!is_null($cache)) {
            return $cache;
        }

        $object = $function($id);

        $this->setCache([$id => $object], $cacheKey);

        return $object;
    }

    protected function wrapDataCache(string $cacheKey, array $data, string $idKey, callable $function): array
    {
        $ids = self::getUniqueIds($data, $idKey);
        list($cached, $ids) = $this->getCache($ids, $cacheKey);
        if (empty($ids)) {
            return $cached;
        }

        $objects = $function($data);

        $this->setCache($objects, $cacheKey);

        return $cached + $objects;
    }

    protected function wrapLevelCache(string $cacheKey, string $cacheLevel, array $ids, callable $function): array
    {
        list($cached, $ids) = $this->getCache($ids, $cacheKey . '_' . $cacheLevel);
        if (empty($ids)) {
            return $cached;
        }

        $levelObjects = array_map(
            function ($levelObject) use ($cacheLevel) {
                $levelObject->setCacheLevel($cacheLevel);
                return $levelObject;
            },
            $function($ids)
        );

        $this->setCache($levelObjects, $cacheKey . '_' . $cacheLevel);

        return $cached + $levelObjects;
    }

    protected function wrapSingleLevelCache(string $cacheKey, string $cacheLevel, int $id, callable $function)
    {
        $cache = $this->getSingleCache($id, $cacheKey . '_' . $cacheLevel);
        if (!is_null($cache)) {
            return $cache;
        }

        $levelObject = $function($id);
        $levelObject->setCacheLevel($cacheLevel);

        $this->setCache([$id => $levelObject], $cacheKey . '_' . $cacheLevel);

        return $levelObject;
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
     * Clear specified cache level or all caches for an entity
     * @param int        $id    Entity id
     * @param array|null $range Cache level to be cleared
     */
    protected function clearCache(int $id, array $range = null): void
    {
        if (empty($range) || (isset($range['mini']) && $range['mini'])) {
            $this->clearSpecificCaches($id, ['mini', 'short', 'full']);
        } else if (isset($range['short']) && $range['short']) {
            $this->clearSpecificCaches($id, ['short', 'full']);
        } else if (isset($range['full']) && $range['full']) {
            $this->clearSpecificCaches($id, ['full']);
        }
        $this->cache->invalidateTags([$this->entityType . 's']);
    }

    /**
     * Clear specific caches
     * @param int   $id        Entity id
     * @param array $specifics Names of the specific caches to be cleared
     */
    private function clearSpecificCaches(int $id, array $specifics): void
    {
        foreach ($specifics as $specific) {
            $this->deleteCache($this->entityType . '_' . $specific, $id);
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
                    if (!in_array($id, $uniqueIds)) {
                        $uniqueIds[] = $id;
                    }
                }
            } else {
                // integer
                if (!in_array($row[$key], $uniqueIds)) {
                    $uniqueIds[] = $row[$key];
                }
            }
        }
        return $uniqueIds;
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
