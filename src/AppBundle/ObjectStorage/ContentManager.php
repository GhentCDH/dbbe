<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Content;
use AppBundle\Model\ContentWithParents;

/**
 * ObjectManager for contents
 * Servicename: content_manager
 */
class ContentManager extends ObjectManager
{
    /**
     * Get single contents with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $rawContents = $this->dbs->getContentsByIds($ids);
        return $this->getWithData($rawContents);
    }

    /**
     * Get single contents with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        $contents = [];
        foreach ($data as $rawContent) {
            if (isset($rawContent['content_id']) && !isset($contents[$rawContent['content_id']])) {
                $contents[$rawContent['content_id']] = new Content(
                    $rawContent['content_id'],
                    $rawContent['name']
                );
            }
        }

        return $contents;
    }

    /**
     * Get contents with parents with all information
     * @param  array $ids
     * @return array
     */
    public function getWithParents(array $ids)
    {
        $contentsWithParents = [];
        $rawContentsWithParents = $this->dbs->getContentsWithParentsByIds($ids);

        foreach ($rawContentsWithParents as $rawContentWithParents) {
            $ids = json_decode($rawContentWithParents['ids']);
            $names = json_decode($rawContentWithParents['names']);

            $rawContents = [];
            foreach (array_keys($ids) as $key) {
                $rawContents[] = [
                    'content_id' => (int)$ids[$key],
                    'name' => $names[$key],
                ];
            }

            $contents = $this->getWithData($rawContents);

            $orderedContents = [];
            foreach ($ids as $id) {
                $orderedContents[] = $contents[(int)$id];
            }

            $contentWithParents = new ContentWithParents($orderedContents);

            $contentsWithParents[$contentWithParents->getId()] = $contentWithParents;
        }

        return $contentsWithParents;
    }

    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'content_id');
        $contentsWithParents = $this->getWithParents($ids);

        // Sort by name
        usort($contentsWithParents, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $contentsWithParents;
    }

    /**
     * Get all contents with parents with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'contents_with_parents',
            ['contents'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getAll());
            }
        );
    }

    /**
     * Get all contents with parents with all information
     * @return array
     */
    public function getAllJson(): array
    {
        return ArrayToJson::arrayToJson($this->getAll());
    }

    /**
     * Get all contents that are dependent on a specific content
     * @param  int   $contentId
     * @return array
     */
    public function getContentDependencies(int $contentId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByContentId($contentId), 'getWithParents');
    }

    /**
     * Add a new content
     * @param  stdClass $data
     * @return ContentWithParents
     */
    public function add(stdClass $data): ContentWithParents
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
                && (
                    !property_exists($data, 'parent')
                    || (
                        $data->parent == null
                        || (property_exists($data->parent, 'id') && is_numeric($data->parent->id))
                    )
                )
            ) {
                $id = $this->dbs->insert(
                    (property_exists($data, 'parent') && $data->parent != null) ? $data->parent->id : null,
                    $data->individualName
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $new = $this->getWithParents([$id])[$id];

            $this->updateModified(null, $new);

            $this->cache->invalidateTags(['contents']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing content
     * @param  int      $id
     * @param  stdClass $data
     * @return ContentWithParents
     */
    public function update(int $id, stdClass $data): ContentWithParents
    {
        $this->dbs->beginTransaction();
        try {
            $contentsWithParents = $this->getWithParents([$id]);
            if (count($contentsWithParents) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Content with id ' . $id .' not found.');
            }
            $old = $contentsWithParents[$id];

            // update content data
            $correct = false;
            if (property_exists($data, 'parent')
                && $data->parent == null
            ) {
                $correct = true;
                $this->dbs->updateParent($id, null);
            }
            if (property_exists($data, 'parent')
                && $data->parent != null
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
                // Prevent cycles
                && $data->parent->id != $id
                // Prevent cycles
                && !in_array($data->parent->id, self::getUniqueIds($this->dbs->getChildIds($id), 'child_id'))
            ) {
                $correct = true;
                $this->dbs->updateParent($id, $data->parent->id);
            }
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
            ) {
                $correct = true;
                $this->dbs->updateName($id, $data->individualName);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $new = $this->getWithParents([$id])[$id];

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['contents']);

            // update Elastic manuscripts
            $manuscriptIds = $this->container->get('manuscript_manager')->getContentDependenciesWithChildren($id, 'getId');
            $this->container->get('manuscript_manager')->updateElasticByIds($manuscriptIds);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Merge two contents
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return ContentWithParents
     */
    public function merge(int $primaryId, int $secondaryId): ContentWithParents
    {
        $contentsWithParents = $this->getWithParents([$primaryId, $secondaryId]);
        if (count($contentsWithParents) != 2) {
            if (!array_key_exists($primaryId, $contentsWithParents)) {
                throw new NotFoundHttpException('Content with id ' . $primaryId .' not found.');
            }
            if (!array_key_exists($secondaryId, $contentsWithParents)) {
                throw new NotFoundHttpException('Content with id ' . $secondaryId .' not found.');
            }
            throw new BadRequestHttpException(
                'Contents with id ' . $primaryId .' and id ' . $secondaryId . ' cannot be merged.'
            );
        }
        list($primary, $secondary) = array_values($contentsWithParents);

        $manuscripts = $this->container->get('manuscript_manager')->getContentDependencies($secondaryId, 'getShort');
        $contents = $this->getContentDependencies($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $contentArray = ArrayToJson::arrayToShortJson($manuscript->getContentsWithParents());
                    $contentArray = array_values(
                        array_filter($contentArray, function ($contentItem) use ($secondaryId, $primaryId) {
                            return $contentItem['id'] !== $secondaryId && $contentItem['id'] !== $primaryId;
                        })
                    );
                    $contentArray[] = ['id' => $primaryId];
                    $this->container->get('manuscript_manager')->update(
                        $manuscript->getId(),
                        json_decode(json_encode(['content' => $contentArray]))
                    );
                }
            }
            if (!empty($contents)) {
                foreach ($contents as $content) {
                    $this->update(
                        $content->getId(),
                        json_decode(json_encode(['parent' => ['id' => $primaryId]]))
                    );
                }
            }
            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if (!empty($manuscripts)) {
                $this->container->get('manuscript_manager')->updateElasticByIds(self::getIds($manuscripts));
            }

            throw $e;
        }

        return $primary;
    }

    /**
     * Delete a content
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $contentsWithParents = $this->getWithParents([$id]);
            if (count($contentsWithParents) == 0) {
                throw new NotFoundHttpException('Content with id ' . $id .' not found.');
            }
            $contentWithParents = $contentsWithParents[$id];

            $this->dbs->delete($id);

            $this->updateModified($contentWithParents, null);

            $this->cache->invalidateTags(['contents']);

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
