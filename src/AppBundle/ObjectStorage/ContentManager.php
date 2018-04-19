<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use AppBundle\Model\Content;
use AppBundle\Model\ContentWithParents;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ContentManager extends ObjectManager
{
    public function getContentsWithParentsByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'content_with_parents');
        if (empty($ids)) {
            return $cached;
        }

        $contentsWithParents = [];
        $rawContentsWithParents = $this->dbs->getContentsWithParentsByIds($ids);

        foreach ($rawContentsWithParents as $rawContentWithParents) {
            $ids = explode(':', $rawContentWithParents['ids']);
            $names = explode(':', $rawContentWithParents['names']);

            $contents = [];
            foreach (array_keys($ids) as $key) {
                $contents[] = new Content($ids[$key], $names[$key]);
            }
            $contentWithParents = new ContentWithParents($contents);

            foreach ($ids as $id) {
                $contentWithParents->addCacheDependency('content.' . $id);
            }

            $contentsWithParents[$contentWithParents->getId()] = $contentWithParents;
        }

        $this->setCache($contentsWithParents, 'content_with_parents');

        return $cached + $contentsWithParents;
    }

    public function getAllContentsWithParents(): array
    {
        $rawIds = $this->dbs->getContentIds();
        $ids = self::getUniqueIds($rawIds, 'content_id');
        $contentsWithParents = $this->getContentsWithParentsByIds($ids);

        // Sort by name
        usort($contentsWithParents, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $contentsWithParents;
    }

    public function getContentsWithParentsByContent(int $contentId): array
    {
        $rawIds = $this->dbs->getContentsByContent($contentId);
        $ids = self::getUniqueIds($rawIds, 'content_id');
        return $this->getContentsWithParentsByIds($ids);
    }

    public function addContentWithParents(stdClass $data): ContentWithParents
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
                && !(
                    property_exists($data, 'parent')
                    && !(
                        $data->parent == null
                        || (property_exists($data->parent, 'id') && is_numeric($data->parent->id))
                    )
                )
            ) {
                $contentId = $this->dbs->insert(
                    (property_exists($data, 'parent') && $data->parent != null) ? $data->parent->id : null,
                    $data->individualName
                );
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $newContentWithParents = $this->getContentsWithParentsByIds([$contentId])[$contentId];

            $this->updateModified(null, $newContentWithParents);

            // update cache
            $this->setCache([$newContentWithParents->getId() => $newContentWithParents], 'content_with_parents');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newContentWithParents;
    }

    public function updateContentWithParents(int $contentId, stdClass $data): ContentWithParents
    {
        $this->dbs->beginTransaction();
        try {
            $contentsWithParents = $this->getContentsWithParentsByIds([$contentId]);
            if (count($contentsWithParents) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Content with id ' . $contentId .' not found.');
            }
            $contentWithParents = $contentsWithParents[$contentId];

            // update content data
            $correct = false;
            if (property_exists($data, 'parent')
                && $data->parent == null
            ) {
                $correct = true;
                $this->dbs->updateParent($contentId, null);
            }
            if (property_exists($data, 'parent')
                && $data->parent != null
                && property_exists($data->parent, 'id')
                && is_numeric($data->parent->id)
                && $data->parent->id != $contentId
            ) {
                $correct = true;
                $this->dbs->updateParent($contentId, $data->parent->id);
            }
            if (property_exists($data, 'individualName')
                && is_string($data->individualName)
            ) {
                $correct = true;
                $this->dbs->updateName($contentId, $data->individualName);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $this->cache->invalidateTags(['content.' . $contentId, 'content_with_parents.' . $contentId]);
            $this->cache->deleteItem('content.' . $contentId);
            $this->cache->deleteItem('content_with_parents.' . $contentId);
            $newContentWithParents = $this->getContentsWithParentsByIds([$contentId])[$contentId];

            $this->updateModified($contentWithParents, $newContentWithParents);

            // update Elastic manuscripts
            $manuscripts = $this->container->get('manuscript_manager')->getManuscriptsDependenciesByContent($contentId);
            $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newContentWithParents;
    }

    public function mergeContentsWithParents(int $primaryId, int $secondaryId): ContentWithParents
    {
        $contentsWithParents = $this->getContentsWithParentsByIds([$primaryId, $secondaryId]);
        if (count($contentsWithParents) != 2) {
            if (!array_key_exists($primaryId, $contentsWithParents)) {
                throw new NotFoundHttpException('Content with id ' . $primaryId .' not found.');
            }
            if (!array_key_exists($secondaryId, $contentsWithParents)) {
                throw new NotFoundHttpException('Content with id ' . $secondaryId .' not found.');
            }
        }
        list($primary, $secondary) = array_values($contentsWithParents);

        $manuscripts = $this->container->get('manuscript_manager')->getManuscriptsDependenciesByContent($secondaryId);
        $contents = $this->getContentsWithParentsByContent($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $this->container->get('manuscript_manager')->updateManuscript(
                        $manuscript->getId(),
                        json_decode(json_encode(['content' => ['id' => $primaryId]]))
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
            throw $e;
        }

        return $primary;
    }

    public function delContent(int $contentId): void
    {
        $this->dbs->beginTransaction();
        try {
            $contentsWithParents = $this->getContentsWithParentsByIds([$contentId]);
            if (count($contentsWithParents) == 0) {
                throw new NotFoundHttpException('Content with id ' . $contentId .' not found.');
            }
            $contentWithParents = $contentsWithParents[$contentId];

            $this->dbs->delete($contentId);

            // empty cache
            $this->cache->invalidateTags(['content.' . $contentId, 'content_with_parents.' . $contentId]);
            $this->cache->deleteItem('content.' . $contentId);
            $this->cache->deleteItem('content_with_parents.' . $contentId);

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
