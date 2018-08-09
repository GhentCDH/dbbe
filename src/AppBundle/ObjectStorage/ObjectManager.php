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
            return $this->getShort(self::getUniqueIds($rawIds, $this->en . '_id'));
        }
        return $this->getMini(self::getUniqueIds($rawIds, $this->en . '_id'));
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
        $this->cache->invalidateTags([$this->en . 's']);
    }

    /**
     * Clear specific caches
     * @param int   $id        Entity id
     * @param array $specifics Names of the specific caches to be cleared
     */
    private function clearSpecificCaches(int $id, array $specifics): void
    {
        foreach ($specifics as $specific) {
            $this->cache->invalidateTags([$this->en . '_' . $specific . '.' . $id]);
            $this->cache->deleteItem($this->en . '_' . $specific . '.' . $id);
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
