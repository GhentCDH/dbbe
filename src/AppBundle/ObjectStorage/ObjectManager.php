<?php

namespace AppBundle\ObjectStorage;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

use AppBundle\Service\DatabaseService\DatabaseServiceInterface;
use AppBundle\Service\ElasticSearchService\ElasticSearchServiceInterface;

class ObjectManager
{
    protected $dbs;
    protected $cache;
    protected $oms;

    public function __construct(
        DatabaseServiceInterface $databaseService,
        CacheItemPoolInterface $cacheItemPool,
        array $objectManagers,
        ElasticSearchServiceInterface $elasticSearchService = null
    ) {
        $this->dbs = $databaseService;
        $this->cache = new TagAwareAdapter($cacheItemPool);
        $this->oms = $objectManagers;
        $this->ess = $elasticSearchService;
    }

    protected static function getUniqueIds(array $rows, string $key, string $filterKey = null, $filterValue = null): array
    {
        $uniqueIds = [];
        foreach ($rows as $row) {
            if (isset($filterKey) && $row[$filterKey] !== $filterValue) {
                continue;
            }
            if (!in_array($row[$key], $uniqueIds)) {
                $uniqueIds[] = $row[$key];
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
                foreach ($item->getCacheDependencies() as $cacheDependency) {
                    $cache->tag($cacheDependency);
                }
            }
            $this->cache->save($cache->set($item));
        }
    }
}
