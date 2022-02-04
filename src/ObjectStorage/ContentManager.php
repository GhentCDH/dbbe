<?php

namespace App\ObjectStorage;

use stdClass;
use Exception;

use App\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Exceptions\DependencyException;
use App\Model\Content;
use App\Model\ContentWithParents;

/**
 * ObjectManager for contents
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
        $personIds = self::getUniqueIds($data, 'person_id');
        $persons = [];
        if (count($personIds) > 0) {
            $persons = $this->container->get(PersonManager::class)->getShort($personIds);
        }

        $contents = [];
        foreach ($data as $rawContent) {
            if (isset($rawContent['content_id']) && !isset($contents[$rawContent['content_id']])) {
                $contents[$rawContent['content_id']] = new Content(
                    $rawContent['content_id'],
                    $rawContent['name'],
                    $rawContent['person_id'] != null ? $persons[$rawContent['person_id']] : null
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
    public function getWithParents(array $ids): array
    {
        $contentsWithParents = [];
        $rawContentsWithParents = $this->dbs->getContentsWithParentsByIds($ids);

        $personIds = [];
        foreach ($rawContentsWithParents as $rawContentWithParents) {
            $personIds = array_merge(
                $personIds,
                array_filter(
                    array_map(
                        function ($id) {
                            return $id != null ? (int)$id : null;
                        },
                        json_decode($rawContentWithParents['person_ids'])
                    )
                )
            );
        }
        $persons = [];
        if (count($personIds) > 0) {
            $persons = $this->container->get(PersonManager::class)->getShort($personIds);
        }

        foreach ($rawContentsWithParents as $rawContentWithParents) {
            $ids = array_map(
                function ($id) {
                    return $id != null ? (int)$id : null;
                },
                json_decode($rawContentWithParents['ids'])
            );
            $names = json_decode($rawContentWithParents['names']);
            $personIds = array_map(
                function ($id) {
                    return $id != null ? (int)$id : null;
                },
                json_decode($rawContentWithParents['person_ids'])
            );

            $contents = [];
            foreach (array_keys($ids) as $key) {
                $contents[] = new Content(
                    $ids[$key],
                    $names[$key],
                    $personIds[$key] != null ? $persons[$personIds[$key]] : null
                );
            }

            $contentWithParents = new ContentWithParents($contents);

            $contentsWithParents[$contentWithParents->getId()] = $contentWithParents;
        }

        return $contentsWithParents;
    }

    /**
     * Get contents with parents with all information
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->getWithParents($ids);
    }

    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'content_id');
        $contentsWithParents = $this->getWithParents($ids);

        // Sort by name
        usort($contentsWithParents, function ($a, $b) {
            return strcmp($a->getDisplayName(), $b->getDisplayName());
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
     * Get all contents that are dependent on a person
     * @param  int   $contentId
     * @return array
     */
    public function getPersonDependencies(int $personId, string $method = 'getId'): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $method);
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
            if (
                (
                    (
                        property_exists($data, 'individualName')
                        && is_string($data->individualName)
                        && (
                            !property_exists($data, 'individualPerson')
                            || $data->individualPerson == null
                        )
                    )
                    || (
                        property_exists($data, 'individualPerson')
                        && $data->individualPerson != null
                        && property_exists($data->individualPerson, 'id')
                        && is_numeric($data->individualPerson->id)
                        && (
                            !property_exists($data, 'individualName')
                            || $data->individualName == null
                        )
                    )
                )
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
                    (property_exists($data, 'individualName') && $data->individualName != null) ? $data->individualName : null,
                    (property_exists($data, 'individualPerson') && $data->individualPerson != null) ? $data->individualPerson->id : null
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
        // Elastic update manuscript and occurrences that have this (manuscript) content
        $manuscripts = $this->container->get(ManuscriptManager::class)->getContentDependenciesWithChildren($id, 'getShort');
        $occurrenceIds = [];
        foreach ($manuscripts as $manuscript) {
            $occurrenceIds = array_merge(
                $occurrenceIds,
                array_map(
                    function ($occurrence) {
                        return $occurrence->getId();
                    },
                    $manuscript->getOccurrences()
                )
            );
        }

        $this->dbs->beginTransaction();
        try {
            $contentsWithParents = $this->getWithParents([$id]);
            if (count($contentsWithParents) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Content with id ' . $id .' not found.');
            }
            $old = $contentsWithParents[$id];

            // only one of individualName, individualPerson can be defined
            if (
                (
                    property_exists($data, 'individualName')
                    && $data->individualName != null
                    && (
                        (
                            $old->getIndividualPerson() != null
                            && (!property_exists($data, 'individualPerson') || $data->individualPerson != null)
                        )
                        || (
                            $old->getIndividualPerson() == null
                            && property_exists($data, 'individualPerson')
                            && $data->individualPerson != null
                        )
                    )
                )
                || (
                    property_exists($data, 'individualPerson')
                    && $data->individualPerson != null
                    && (
                        (
                            $old->getIndividualName() != null
                            && (!property_exists($data, 'individualName') || $data->individualName != null)
                        )
                        || (
                            $old->getIndividualName() == null
                            && property_exists($data, 'individualName')
                            && $data->individualName != null
                        )
                    )
                )
            ) {
                throw new BadRequestHttpException('Incorrect data.');
            }

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
                && (is_string($data->individualName) || $data->individualName == null)
            ) {
                $correct = true;
                $this->dbs->updateName($id, $data->individualName);
            }
            if (property_exists($data, 'individualPerson')
                && $data->individualPerson == null
            ) {
                $correct = true;
                $this->dbs->updatePerson($id, null);
            }
            if (property_exists($data, 'individualPerson')
                && $data->individualPerson != null
                && property_exists($data->individualPerson, 'id')
                && is_numeric($data->individualPerson->id)
            ) {
                $correct = true;
                $this->dbs->updatePerson($id, $data->individualPerson->id);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new content data
            $new = $this->getWithParents([$id])[$id];

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['contents']);

            // update Elastic dependencies
            $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($manuscripts));
            $this->container->get(OccurrenceManager::class)->updateElasticByIds($occurrenceIds);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // Reset Elastic dependencies
            if (!empty($manuscripts)) {
                $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($manuscripts));
                $this->container->get(OccurrenceManager::class)->updateElasticByIds($occurrenceIds);
            }

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
        $primary = $contentsWithParents[$primaryId];

        $manuscripts = $this->container->get(ManuscriptManager::class)->getContentDependenciesWithChildren($secondaryId, 'getShort');
        // Elastic update occurrences that have this person as manuscript content
        $occurrenceIds = [];
        foreach ($manuscripts as $manuscript) {
            $occurrenceIds = array_merge(
                $occurrenceIds,
                array_map(
                    function ($occurrence) {
                        return $occurrence->getId();
                    },
                    $manuscript->getOccurrences()
                )
            );
        }
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
                    $this->container->get(ManuscriptManager::class)->update(
                        $manuscript->getId(),
                        json_decode(json_encode(['contents' => $contentArray]))
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

            // update Elastic dependencies
            $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($manuscripts));
            $this->container->get(OccurrenceManager::class)->updateElasticByIds($occurrenceIds);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // Reset Elastic dependencies
            if (!empty($manuscripts)) {
                $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($manuscripts));
                $this->container->get(OccurrenceManager::class)->updateElasticByIds($occurrenceIds);
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
