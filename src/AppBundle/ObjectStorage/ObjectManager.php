<?php

namespace AppBundle\ObjectStorage;

use Exception;

use Psr\Cache\CacheItemPoolInterface;
use AppBundle\Model\IdJsonInterface;

use Symfony\Component\Cache\Adapter\TagAwareAdapter;

use AppBundle\Service\DatabaseService\DatabaseServiceInterface;
use AppBundle\Service\ElasticSearchService\ElasticSearchServiceInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ObjectManager
{
    protected $dbs;
    protected $cache;
    protected $oms;
    protected $ess;
    protected $ts;

    public function __construct(
        DatabaseServiceInterface $databaseService,
        CacheItemPoolInterface $cacheItemPool,
        array $objectManagers,
        ElasticSearchServiceInterface $elasticSearchService = null,
        TokenStorageInterface $tokenStorage = null
    ) {
        $this->dbs = $databaseService;
        $this->cache = new TagAwareAdapter($cacheItemPool);
        $this->oms = $objectManagers;
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
                foreach ($item->getCacheDependencies() as $cacheDependency) {
                    $cache->tag($cacheDependency);
                }
            }
            $this->cache->save($cache->set($item));
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
}
