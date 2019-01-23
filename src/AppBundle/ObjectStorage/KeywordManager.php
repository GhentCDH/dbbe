<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Keyword;
use AppBundle\Model\Person;
use AppBundle\Utils\ArrayToJson;

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

    public function getByType(string $type): array
    {
        switch ($type) {
            case 'subject':
                $rawIds = $this->dbs->getSubjectIds();
                break;
            case 'type':
                $rawIds = $this->dbs->getTypeIds();
                break;
        }

        $ids = self::getUniqueIds($rawIds, 'keyword_id');
        $keywords = $this->get($ids);

        // Sort by name
        usort($keywords, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $keywords;
    }

    public function getByTypeShortJson(string $type): array
    {
        return $this->wrapArrayTypeCache(
            $type . '_keywords',
            $type,
            ['keywords'],
            function ($type) {
                return ArrayToJson::arrayToShortJson($this->getByType($type));
            }
        );
    }

    public function getByTypeJson(string $type): array
    {
        return ArrayToJson::arrayToJson($this->getByType($type));
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
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['keywords']);

            // update Elastic occurrences
            $this->container->get('occurrence_manager')->updateElasticByIds(
                $this->container->get('occurrence_manager')->getKeywordDependencies($id, 'getId')
            );

            // update Elastic types
            $this->container->get('type_manager')->updateElasticByIds(
                $this->container->get('type_manager')->getKeywordDependencies($id, 'getId')
            );

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Migrate a keyword to a person
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return Person
     */
    public function migratePerson(int $primaryId, int $secondaryId): Person
    {
        $keywords = $this->get([$primaryId]);
        if (count($keywords) != 1) {
            throw new NotFoundHttpException('Keyword with id ' . $primaryId .' not found.');
        }
        $keyword = $keywords[$primaryId];
        // Will throw an exception if not found
        $person = $this->container->get('person_manager')->getFull($secondaryId);

        $occurrences = $this->container->get('occurrence_manager')->getKeywordDependencies($primaryId, 'getShort');
        $types = $this->container->get('type_manager')->getKeywordDependencies($primaryId, 'getShort');
        $poems = $occurrences + $types;

        $this->dbs->beginTransaction();
        try {
            if (!empty($poems)) {
                foreach ($poems as $poem) {
                    $keywordArray = ArrayToJson::arrayToShortJson($poem->getKeywordSubjects());
                    // filter out the keywords that are not equal to the selected keyword
                    $keywordArray = array_values(
                        array_filter(
                            $keywordArray,
                            function ($keywordItem) use ($primaryId) {
                                return $keywordItem['id'] != $primaryId;
                            }
                        )
                    );
                    $personArray = ArrayToJson::arrayToShortJson($poem->getPersonSubjects());
                    // filter out the keywords that are not equal to the selected person
                    // (preventing a possible duplicate)
                    $personArray = array_values(
                        array_filter(
                            $personArray,
                            function ($personItem) use ($secondaryId) {
                                return $personItem['id'] != $secondaryId;
                            }
                        )
                    );
                    $personArray[] = ['id' => $secondaryId];
                    $this->container->get($poem::CACHENAME . '_manager')->update(
                        $poem->getId(),
                        json_decode(json_encode([
                            'keywordSubjects' => $keywordArray,
                            'personSubjects' => $personArray,
                        ]))
                    );
                }
            }
            $this->delete($primaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            if (!empty($occurrences)) {
                $this->container->get('occurrence_manager')->updateElasticByIds(array_keys($poems));
            }
            if (!empty($types)) {
                $this->container->get('type_manager')->updateElasticByIds(array_keys($poems));
            }

            throw $e;
        }

        return $person;
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

            $this->updateModified($old, null);

            $this->cache->invalidateTags(['keywords']);

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
