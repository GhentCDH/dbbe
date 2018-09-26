<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Keyword;

/**
 * ObjectManager for keywords
 * Servicename: keyword_manager
 */
class KeywordManager extends ObjectManager
{
    /**
     * Get single keywords with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Keyword::CACHENAME,
            $ids,
            function ($ids) {
                $keywords = [];
                $rawKeywords = $this->dbs->getKeywordsByIds($ids);

                foreach ($rawKeywords as $rawKeyword) {
                    $keywords[$rawKeyword['keyword_id']] = new Keyword(
                        $rawKeyword['keyword_id'],
                        $rawKeyword['name']
                    );
                }

                return $keywords;
            }
        );
    }

    /**
     * Get all subject keywords with all information
     * @return array
     */
    public function getAllSubjectKeywords(): array
    {
        return $this->wrapArrayCache(
            'subject_keywords',
            ['keywords'],
            function () {
                $rawIds = $this->dbs->getSubjectIds();
                $ids = self::getUniqueIds($rawIds, 'keyword_id');
                $keywords = $this->get($ids);

                // Sort by name
                usort($keywords, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $keywords;
            }
        );
    }

    /**
     * Get all subject keywords with all information
     * @return array
     */
    public function getAllTypeKeywords(): array
    {
        return $this->wrapArrayCache(
            'type_keywords',
            ['keywords'],
            function () {
                $rawIds = $this->dbs->getTypeIds();
                $ids = self::getUniqueIds($rawIds, 'keyword_id');
                $keywords = $this->get($ids);

                // Sort by name
                usort($keywords, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $keywords;
            }
        );
    }

    /**
     * Clear cache
     * @param array $ids
     */
    public function reset(array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteCache(Keyword::CACHENAME, $id);
        }

        $this->get($ids);

        $this->cache->invalidateTags(['keywords']);
    }

    /**
     * Add a new keyword
     * @param  stdClass $data
     * @return Keyword
     */
    public function add(stdClass $data): Keyword
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'isSubject')
                && is_bool($data->isSubject)
            ) {
                $id = $this->dbs->insert($data->name, $data->isSubject);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified(null, $new);

            // update cache
            $this->cache->invalidateTags(['keywords']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing keyword
     * @param  int      $id
     * @param  stdClass $data
     * @return Keyword
     */
    public function update(int $id, stdClass $data): Keyword
    {
        $this->dbs->beginTransaction();
        try {
            $keywords = $this->get([$id]);
            if (count($keywords) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Keyword with id ' . $id .' not found.');
            }
            $old = $keywords[$id];

            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $this->dbs->updateName($id, $data->name);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $this->deleteCache(Keyword::CACHENAME, $id);
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);

            // update Elastic occurrences
            $occurrences = $this->container->get('occurrence_manager')->getKeywordDependencies($id, true);
            $this->container->get('occurrence_manager')->elasticIndex($occurrences);

            // TODO: types

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Delete a keyword
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $keywords = $this->get([$id]);
            if (count($keywords) == 0) {
                throw new NotFoundHttpException('Keyword with id ' . $id .' not found.');
            }
            $old = $keywords[$id];

            $this->dbs->delete($id);

            // empty cache
            $this->cache->invalidateTags(['keywords']);
            $this->deleteCache(Keyword::CACHENAME, $id);

            $this->updateModified($old, null);

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
