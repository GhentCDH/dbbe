<?php

namespace AppBundle\ObjectStorage;

use DateTime;
use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\OnlineSource;

/**
 * ObjectManager for online sources
 * Servicename: online_source_manager
 */
class OnlineSourceManager extends EntityManager
{
    /**
     * Get online sources with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            OnlineSource::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $onlineSources = [];
                $rawOnlineSources = $this->dbs->getMiniInfoByIds($ids);

                foreach ($rawOnlineSources as $rawOnlineSource) {
                    $onlineSources[$rawOnlineSource['online_source_id']] = new OnlineSource(
                        $rawOnlineSource['online_source_id'],
                        $rawOnlineSource['url'],
                        $rawOnlineSource['institution_name'],
                        new DateTime($rawOnlineSource['last_accessed'])
                    );
                }

                return $onlineSources;
            }
        );
    }

    /**
     * Get online sources with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->getMini($ids);
    }

    /**
     * Get a single online source with all information
     * @param  int        $id
     * @return OnlineSource
     */
    public function getFull(int $id): OnlineSource
    {
        return $this->wrapSingleLevelCache(
            OnlineSource::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic information
                $onlineSources = $this->getShort([$id]);
                if (count($onlineSources) == 0) {
                    throw new NotFoundHttpException('Online source with id ' . $id .' not found.');
                }

                $this->setInverseBibliographies($onlineSources);

                return $onlineSources[$id];
            }
        );
    }

    /**
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMini(string $sortFunction = null): array
    {
        return parent::getAllMini($sortFunction == null ? 'getDescription' : $sortFunction);
    }

    /**
     * @return array
     */
    public function getAllShort(): array
    {
        return parent::getAllShort();
    }

    /**
     * Get the online source that is dependent on a specific institution
     * This online source and instution will have the same id
     * @param  int   $institutionId
     * @return array
     */
    public function getInstitutionDependencies(int $institutionId): array
    {
        return $this->getDependencies([$institutionId], 'getMini');
    }

    /**
     * Get all online sources that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getReferenceDependencies(array $referenceIds): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByReferenceIds($referenceIds), 'getMini');
    }

    /**
     * Add a new online source
     * @param  stdClass $data
     * @return OnlineSource
     */
    public function add(stdClass $data): OnlineSource
    {
        if (!property_exists($data, 'url')
            || !is_string($data->url)
            || empty($data->url)
            || !property_exists($data, 'name')
            || !is_string($data->name)
            || empty($data->name)
            || !property_exists($data, 'lastAccessed')
            || !is_string($data->lastAccessed)
            || empty($data->lastAccessed)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new online source');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->url, $data->name, $data->lastAccessed);

            unset($data->url);
            unset($data->name);
            unset($data->lastAccessed);

            $new = $this->update($id, $data, true);

            // update cache
            $this->cache->invalidateTags([$this->entityType . 's']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update new or existing online source
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new online source
     * @return OnlineSource
     */
    public function update(int $id, stdClass $data, bool $isNew = false): OnlineSource
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Online source with id ' . $id .' not found.');
            }

            $cacheReload = [
                'mini' => $isNew,
            ];
            if (property_exists($data, 'url')) {
                // Url is a required field
                if (!is_string($data->url) || empty($data->url)) {
                    throw new BadRequestHttpException('Incorrect base url data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateUrl($id, $data->url);
            }
            if (property_exists($data, 'name')) {
                // Name is a required field
                if (!is_string($data->name) || empty($data->name)) {
                    throw new BadRequestHttpException('Incorrect name data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateName($id, $data->name);
            }
            if (property_exists($data, 'lastAccessed')) {
                // Last accessed is a required field
                if (!is_string($data->lastAccessed) || empty($data->lastAccessed)) {
                    throw new BadRequestHttpException('Incorrect lastAccessed data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateLastAccessed($id, $data->lastAccessed);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $this->clearCache($id, $cacheReload);
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            // (re-)index in elastic search
            $this->ess->add($new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($new)) {
                $this->reset([$id]);
            }
            throw $e;
        }

        return $new;
    }

    /**
     * Delete an online source
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws a not found exception when not found
            $old = $this->getFull($id);

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            // empty cache and remove from elasticsearch
            $this->reset([$id]);
            $this->cache->invalidateTags([$this->entityType . 's']);

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
