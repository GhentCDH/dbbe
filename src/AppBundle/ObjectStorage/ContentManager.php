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

class ContentManager extends ObjectManager
{
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Content::CACHENAME,
            $ids,
            function ($ids) {
                $contents = [];
                $rawContents = $this->dbs->getContentsByIds($ids);
                $contents = $this->getWithData($rawContents);

                return $contents;
            }
        );
    }

    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            Content::CACHENAME,
            $data,
            'content_id',
            function ($data) {
                $contents = [];
                foreach ($data as $rawContent) {
                    if (isset($rawContent['content_id'])) {
                        $contents[$rawContent['content_id']] = new Content(
                            $rawContent['content_id'],
                            $rawContent['name']
                        );
                    }
                }

                return $contents;
            }
        );
    }

    public function getWithParents(array $ids)
    {
        return $this->wrapCache(
            ContentWithParents::CACHENAME,
            $ids,
            function ($ids) {
                $contentsWithParents = [];
                $rawContentsWithParents = $this->dbs->getContentsWithParentsByIds($ids);

                foreach ($rawContentsWithParents as $rawContentWithParents) {
                    $ids = explode(':', $rawContentWithParents['ids']);
                    $names = explode(':', $rawContentWithParents['names']);

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
        );
    }

    public function getAllContentsWithParents(): array
    {
        return $this->wrapArrayCache(
            'contents_with_parents',
            ['contents'],
            function () {
                $rawIds = $this->dbs->getContentIds();
                $ids = self::getUniqueIds($rawIds, 'content_id');
                $contentsWithParents = $this->getWithParents($ids);

                // Sort by name
                usort($contentsWithParents, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return $contentsWithParents;
            }
        );
    }

    public function getContentsWithParentsByContent(int $contentId): array
    {
        $rawIds = $this->dbs->getContentsByContentId($contentId);
        $ids = self::getUniqueIds($rawIds, 'content_id');
        return $this->getWithParents($ids);
    }

    public function addContentWithParents(stdClass $data): ContentWithParents
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

            // update cache
            $this->cache->invalidateTags(['contents']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    public function updateContentWithParents(int $id, stdClass $data): ContentWithParents
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
            $this->deleteCache(Content::CACHENAME, $id);
            $this->deleteCache(ContentWithParents::CACHENAME, $id);
            $new = $this->getWithParents([$id])[$id];

            $this->updateModified($old, $new);

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getContentDependenciesWithChildren($id, true);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    public function mergeContentsWithParents(int $primaryId, int $secondaryId): ContentWithParents
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

        $manuscripts = $this->container->get('manuscript_manager')->getContentDependencies($secondaryId, true);
        $contents = $this->getContentsWithParentsByContent($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $contentArray = ArrayToJson::arrayToShortJson($manuscript->getContentsWithParents());
                    $contentArray = array_values(array_filter($contentArray, function ($contentItem) use ($secondaryId, $primaryId) {
                        return $contentItem['id'] !== $secondaryId && $contentItem['id'] !== $primaryId;
                    }));
                    $contentArray[] = ['id' => $primaryId];
                    $this->container->get('manuscript_manager')->update(
                        $manuscript->getId(),
                        json_decode(json_encode(['content' => $contentArray]))
                    );
                }
            }
            if (!empty($contents)) {
                foreach ($contents as $content) {
                    $this->updateContentWithParents(
                        $content->getId(),
                        json_decode(json_encode(['parent' => ['id' => $primaryId]]))
                    );
                }
            }
            $this->delContent($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            // TODO: invalidate caches and revert elasticsearch updates when rolling back, because part of them can be updated
            throw $e;
        }

        return $primary;
    }

    public function delContent(int $contentId): void
    {
        $this->dbs->beginTransaction();
        try {
            $contentsWithParents = $this->getWithParents([$contentId]);
            if (count($contentsWithParents) == 0) {
                throw new NotFoundHttpException('Content with id ' . $contentId .' not found.');
            }
            $contentWithParents = $contentsWithParents[$contentId];

            $this->dbs->delete($contentId);

            // empty cache
            $this->cache->invalidateTags(['contents']);
            $this->deleteCache(Content::CACHENAME, $contentId);
            $this->deleteCache(ContentWithParents::CACHENAME, $contentId);

            $this->updateModified($contentWithParents, null);

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
