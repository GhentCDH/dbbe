<?php

namespace AppBundle\ObjectStorage;

use Exception;

use Psr\Cache\CacheItemPoolInterface;

use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

use AppBundle\Model\IdJsonInterface;
use AppBundle\Service\DatabaseService\DatabaseServiceInterface;
use AppBundle\Service\ElasticSearchService\ElasticSearchServiceInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

    protected function getCache(array $ids, string $cacheKey): array
    {
        $cached = [];

        foreach ($ids as $key => $id) {
            $cache = $this->cache->getItem($cacheKey . '.' . $id);
            if ($cache->isHit()) {
                $cached[$id] = $cache->get();
                unset($ids[$key]);
            }
        }

        return [$cached, $ids];
    }

    protected function setCache(array $items, string $cacheKey): void
    {
        foreach ($items as $id => $item) {
            $cache = $this->cache->getItem($cacheKey . '.' . $id);
            if (method_exists($item, 'getCacheDependencies')) {
                $cache->tag($item->getCacheDependencies());
            }
            try {
                $this->cache->save($cache->set($item));
            } catch (\Exception $e) {
                var_dump($e);
            }
        }
    }

    protected function clearCache(string $cacheKey, int $id, array $range = null): void
    {
        if (empty($range) || $range['mini']) {
            $this->cache->invalidateTags([$cacheKey . '_mini.' . $id]);
            $this->cache->deleteItem($cacheKey . '_mini.' . $id);
        }
        if (empty($range) || $range['mini'] || $range['short']) {
            $this->cache->invalidateTags([$cacheKey . '_short.' . $id]);
            $this->cache->deleteItem($cacheKey . '_short.' . $id);
        }
        if (empty($range) || $range['mini'] || $range['short'] || $range['extended']) {
            $this->cache->invalidateTags([$cacheKey . '.' . $id]);
            $this->cache->deleteItem($cacheKey . '.' . $id);
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
