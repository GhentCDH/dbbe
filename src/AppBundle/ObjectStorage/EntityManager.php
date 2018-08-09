<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Entity;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Identification;
use AppBundle\Model\Identifier;

class EntityManager extends ObjectManager
{
    /**
     * entity name
     * @var string
     */
    protected $en;

    public function getAllShort(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, $this->en . '_id');
        return $this->getShort($ids);
    }

    protected function setPublics(array &$entities): void
    {
        $rawPublics = $this->dbs->getPublics(self::getIds($entities));
        foreach ($rawPublics as $rawPublic) {
            $entities[$rawPublic['entity_id']]
                // default: true (if no value is set in the database)
                ->setPublic(isset($rawPublic['public']) ? $rawPublic['public'] : true);
        }
    }

    protected function setComments(array &$entities): void
    {
        $rawComments = $this->dbs->getComments(self::getIds($entities));
        foreach ($rawComments as $rawComment) {
            $entities[$rawComment['entity_id']]
                ->setPublicComment($rawComment['public_comment'])
                ->setPrivateComment($rawComment['private_comment']);
        }
    }

    protected function setIdentifications(array &$entities): void
    {
        $rawIdentifiers = $this->dbs->getIdentifications(self::getIds($entities));
        foreach ($rawIdentifiers as $rawIdentifier) {
            $entities[$rawIdentifier['entity_id']]->addIdentification(
                new Identification(
                    new Identifier(
                        $rawIdentifier['identifier_id'],
                        $rawIdentifier['system_name'],
                        $rawIdentifier['name'],
                        $rawIdentifier['is_primary'],
                        $rawIdentifier['link']
                    ),
                    json_decode($rawIdentifier['identifiers']),
                    json_decode($rawIdentifier['authority_ids']),
                    json_decode($rawIdentifier['identifier_ids'])
                )
            );
        }
    }

    protected function setBibliographies(array &$entities): void
    {
        $rawBibliographies = $this->dbs->getBibliographies(self::getIds($entities));
        if (!empty($rawBibliographies)) {
            $bookIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book');
            $articleIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'article');
            $bookChapterIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book_chapter');
            $onlineSourceIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'online_source');

            $bookBibliographies = $this->container->get('bibliography_manager')->getBookBibliographiesByIds($bookIds);
            $articleBibliographies = $this->container->get('bibliography_manager')->getArticleBibliographiesByIds($articleIds);
            $bookChapterBibliographies = $this->container->get('bibliography_manager')->getBookChapterBibliographiesByIds($bookChapterIds);
            $onlineSourceBibliographies = $this->container->get('bibliography_manager')->getOnlineSourceBibliographiesByIds($onlineSourceIds);

            $bibliographies = $bookBibliographies + $articleBibliographies + $bookChapterBibliographies + $onlineSourceBibliographies;

            foreach ($rawBibliographies as $rawBibliography) {
                $biblioId = $rawBibliography['reference_id'];
                // Add cache dependencies
                switch ($rawBibliography['type']) {
                    case 'book':
                        $entities[$rawBibliography['entity_id']]
                            ->addCacheDependency('book_bibliography.' . $biblioId);
                        break;
                    case 'article':
                        $entities[$rawBibliography['entity_id']]
                            ->addCacheDependency('article_bibliography.' . $biblioId);
                        break;
                    case 'book_chapter':
                        $entities[$rawBibliography['entity_id']]
                            ->addCacheDependency('book_chapter_bibliography.' . $biblioId);
                        break;
                    case 'online_source':
                        $entities[$rawBibliography['entity_id']]
                            ->addCacheDependency('online_source_bibliography.' . $biblioId);
                        break;
                }
                // Add cache dependency dependencies
                foreach ($bibliographies[$biblioId]->getCacheDependencies() as $cacheDependency) {
                    $entities[$rawBibliography['entity_id']]
                        ->addCacheDependency($cacheDependency);
                }
                // Add to entity
                $entities[$rawBibliography['entity_id']]
                    ->addBibliography($bibliographies[$biblioId]);
            }
        }
    }

    /**
     * Clear cache and (re-)index elasticsearch
     * When something goes wrong with an update
     * @param array $ids
     */
    public function reset(array $ids): void
    {
        foreach ($ids as $id) {
            $this->clearCache($id);
        }

        $this->elasticIndexByIds($ids);
    }

    /**
     * (Re-)index elasticsearch
     * @param array $miniEntities
     */
    public function elasticIndex(array $miniEntities): void
    {
        $entityIds = array_map(
            function ($miniEntity) {
                return $miniEntity->getId();
            },
            $miniEntities
        );
        $this->elasticIndexByIds($entityIds);
    }

    /**
     * (Re-)index elasticsearch
     * @param  array  $ids
     */
    private function elasticIndexByIds(array $ids): void
    {
        $this->ess->add($this->getShort($ids));
    }

    protected function updatePublic(Entity $entity, bool $public): void
    {
        $this->dbs->updatePublic($entity->getId(), $public);
    }

    protected function updateDate(Entity $entity, string $type, FuzzyDate $currentDate = null, stdClass $newdate = null): void
    {
        if (empty($newdate)) {
            $this->dbs->deleteDate($entity->getId(), $type);
        } elseif (!property_exists($newdate, 'floor') || (!empty($newdate->floor) && !is_string($newdate->floor))
            || !property_exists($newdate, 'ceiling') || (!empty($newdate->ceiling) && !is_string($newdate->ceiling))
        ) {
            throw new BadRequestHttpException('Incorrect date data.');
        } else {
            $dbDate = '('
                . (empty($newdate->floor) ? '-infinity' : $newdate->floor)
                . ', '
                . (empty($newdate->ceiling) ? 'infinity' : $newdate->ceiling)
                . ')';
            if (!isset($currentDate) || $currentDate->isEmpty()) {
                $this->dbs->insertDate($entity->getId(), $type, $dbDate);
            } else {
                $this->dbs->updateDate($entity->getId(), $type, $dbDate);
            }
        }
    }

    protected function updatePublicComment(Entity $entity, string $publicComment = null): void
    {
        if (empty($publicComment)) {
            if (!empty($entity->getPublicComment())) {
                $this->dbs->updatePublicComment($entity->getId(), '');
            }
        } else {
            $this->dbs->updatePublicComment($entity->getId(), $publicComment);
        }
    }

    protected function updatePrivateComment(Entity $entity, string $privateComment = null): void
    {
        if (empty($privateComment)) {
            if (!empty($entity->getPrivateComment())) {
                $this->dbs->updatePrivateComment($entity->getId(), '');
            }
        } else {
            $this->dbs->updatePrivateComment($entity->getId(), $privateComment);
        }
    }

    protected function updateIdentification(Entity $entity, Identifier $identifier, string $value): void
    {
        if (!empty($value) && !preg_match(
            '/' . $identifier->getRegex() . '/',
            $value
        )) {
            throw new BadRequestHttpException('Incorrect identification data.');
        }


        if ($identifier->getVolumes() > 1) {
            $newIdentifications = empty($value) ? [] : explode(', ', $value);
            $volumeArray = [];
            foreach ($newIdentifications as $identification) {
                $volume = explode('.', $identification)[0];
                if (in_array($volume, $volumeArray)) {
                    throw new BadRequestHttpException('Duplicate identification entry.');
                } else {
                    $volumeArray[] = $volume;
                }
            }

            $newArray = [];
            foreach ($newIdentifications as $newIdentification) {
                list($volume, $id) = explode('.', $newIdentification);
                $newArray[$volume] = $id;
            }

            $currentIdentifications = empty($entity->getIdentifications()[$identifier->getSystemName()]) ? [] : $entity->getIdentifications()[$identifier->getSystemName()]->getIdentifications();
            $currentArray = [];
            foreach ($currentIdentifications as $currentIdentification) {
                list($volume, $id) = explode('.', $currentIdentification);
                $currentArray[$volume] = $id;
            }

            $delArray = [];
            $upsertArray = [];
            for ($volume = 0; $volume < $identifier->getVolumes(); $volume++) {
                $romanVolume = Identification::numberToRoman($volume +1);
                // No old and no new value
                if (!isset($currentArray[$romanVolume]) && !isset($newArray[$romanVolume])) {
                    continue;
                }
                // Old value === new value
                if (isset($currentArray[$romanVolume]) && isset($newArray[$romanVolume])
                    && $currentArray[$romanVolume] === $newArray[$romanVolume]
                ) {
                    continue;
                }
                // Old value, but no new value
                if (isset($currentArray[$romanVolume]) && !isset($newArray[$romanVolume])) {
                    $delArray[] = $volume;
                    continue;
                }
                // No old or different value
                $upsertArray[$volume] = $newArray[$romanVolume];
            }

            $this->dbs->beginTransaction();
            try {
                foreach ($delArray as $volume) {
                    $this->dbs->delIdentification($entity->getId(), $identifier->getId(), $volume);
                }
                foreach ($upsertArray as $volume => $value) {
                    $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), $volume, $value);
                }

                // commit transaction
                $this->dbs->commit();
            } catch (Exception $e) {
                $this->dbs->rollBack();
                throw $e;
            }
        } else {
            $this->dbs->beginTransaction();
            try {
                // Old value, but no new value
                if (!empty($entity->getIdentifications()[$identifier->getSystemName()]) && empty($value)) {
                    $this->dbs->delIdentification($entity->getId(), $identifier->getId(), 0);
                } elseif ((empty($entity->getIdentifications()[$identifier->getSystemName()]) && !empty($value)) // No old value
                    || (!empty($entity->getIdentifications()[$identifier->getSystemName()]) && !empty($value) && $entity->getIdentifications()[$identifier->getSystemName()] !== $value) // Different old value
                ) {
                    $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), 0, $value);
                }

                // commit transaction
                $this->dbs->commit();
            } catch (Exception $e) {
                $this->dbs->rollBack();
                throw $e;
            }
        }
    }

    protected function updateBibliography(Entity $entity, stdClass $bibliography): void
    {
        foreach (['book', 'article', 'bookChapter', 'onlineSource'] as $bibType) {
            $plurBibType = $bibType . 's';
            if (!property_exists($bibliography, $plurBibType) || !is_array($bibliography->$plurBibType)) {
                throw new BadRequestHttpException('Incorrect bibliography data.');
            }
            foreach ($bibliography->$plurBibType as $bib) {
                if (!is_object($bib)
                    || (property_exists($bib, 'id') && !is_numeric($bib->id))
                    || !property_exists($bib, $bibType) || !is_object($bib->$bibType)
                    || !property_exists($bib->$bibType, 'id') || !is_numeric($bib->$bibType->id)
                ) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                if (in_array($bibType, ['book', 'article', 'bookChapter'])) {
                    if (!property_exists($bib, 'startPage') || !(empty($bib->startPage) || is_string($bib->startPage))
                        || !property_exists($bib, 'endPage')  || !(empty($bib->endPage) || is_string($bib->endPage))
                        || (property_exists($bib, 'rawPages') && !(empty($bib->rawPages) || is_string($bib->rawPages)))
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                } else {
                    if (!property_exists($bib, 'relUrl') || !is_string($bib->relUrl)
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                }
            }
        }

        $updateIds = [];
        $origBibIds = array_keys($entity->getBibliographies());
        // Add and update
        foreach ($bibliography->books as $bookBib) {
            if (!property_exists($bookBib, 'id')) {
                $this->container->get('bibliography_manager')->addBookBibliography(
                    $entity->getId(),
                    $bookBib->book->id,
                    self::certainString($bookBib, 'startPage'),
                    self::certainString($bookBib, 'endPage')
                );
            } elseif (in_array($bookBib->id, $origBibIds)) {
                $updateIds[] = $bookBib->id;
                $this->container->get('bibliography_manager')->updateBookBibliography(
                    $bookBib->id,
                    $bookBib->book->id,
                    self::certainString($bookBib, 'startPage'),
                    self::certainString($bookBib, 'endPage'),
                    self::certainString($bookBib, 'rawPages')
                );
            } else {
                throw new NotFoundHttpException(
                    'Bibliography with id "' . $bookBib->id . '" not found '
                    . ' in entity with id "' . $entity->getId() . '".'
                );
            }
        }
        foreach ($bibliography->articles as $articleBib) {
            if (!property_exists($articleBib, 'id')) {
                $this->container->get('bibliography_manager')->addArticleBibliography(
                    $entity->getId(),
                    $articleBib->article->id,
                    self::certainString($articleBib, 'startPage'),
                    self::certainString($articleBib, 'endPage')
                );
            } elseif (in_array($articleBib->id, $origBibIds)) {
                $updateIds[] = $articleBib->id;
                $this->container->get('bibliography_manager')->updateArticleBibliography(
                    $articleBib->id,
                    $articleBib->article->id,
                    self::certainString($articleBib, 'startPage'),
                    self::certainString($articleBib, 'endPage'),
                    self::certainString($articleBib, 'rawPages')
                );
            } else {
                throw new NotFoundHttpException(
                    'Bibliography with id "' . $articleBib->id . '" not found '
                    . ' in entity with id "' . $entity->getId() . '".'
                );
            }
        }
        foreach ($bibliography->bookChapters as $bookChapterBib) {
            if (!property_exists($bookChapterBib, 'id')) {
                $this->container->get('bibliography_manager')->addBookChapterBibliography(
                    $entity->getId(),
                    $bookChapterBib->bookChapter->id,
                    self::certainString($bookChapterBib, 'startPage'),
                    self::certainString($bookChapterBib, 'endPage')
                );
            } elseif (in_array($bookChapterBib->id, $origBibIds)) {
                $updateIds[] = $bookChapterBib->id;
                $this->container->get('bibliography_manager')->updateBookChapterBibliography(
                    $bookChapterBib->id,
                    $bookChapterBib->bookChapter->id,
                    self::certainString($bookChapterBib, 'startPage'),
                    self::certainString($bookChapterBib, 'endPage'),
                    self::certainString($bookChapterBib, 'rawPages')
                );
            } else {
                throw new NotFoundHttpException(
                    'Bibliography with id "' . $bookChapterBib->id . '" not found '
                    . ' in entity with id "' . $entity->getId() . '".'
                );
            }
        }
        foreach ($bibliography->onlineSources as $onlineSourceBib) {
            if (!property_exists($onlineSourceBib, 'id')) {
                $this->container->get('bibliography_manager')->addOnlineSourceBibliography(
                    $entity->getId(),
                    $onlineSourceBib->onlineSource->id,
                    self::certainString($onlineSourceBib, 'relUrl')
                );
            } elseif (in_array($onlineSourceBib->id, $origBibIds)) {
                $updateIds[] = $onlineSourceBib->id;
                $this->container->get('bibliography_manager')->updateOnlineSourceBibliography(
                    $onlineSourceBib->id,
                    $onlineSourceBib->onlineSource->id,
                    self::certainString($onlineSourceBib, 'relUrl')
                );
            } else {
                throw new NotFoundHttpException(
                    'Bibliography with id "' . $onlineSourceBib->id . '" not found '
                    . ' in entity with id "' . $entity->getId() . '".'
                );
            }
        }
        // delete
        $delIds = [];
        foreach ($origBibIds as $origId) {
            if (!in_array($origId, $updateIds)) {
                $delIds[] = $origId;
            }
        }
        if (count($delIds) > 0) {
            $this->container->get('bibliography_manager')->delBibliographies(
                array_filter(
                    $entity->getBibliographies(),
                    function ($bibliography) use ($delIds) {
                        return in_array($bibliography->getId(), $delIds);
                    }
                )
            );
        }
    }

    protected static function getIds(array $entities): array
    {
        return array_map(function ($entity) {
            return $entity->getId();
        }, $entities);
    }

    private static function certainString(stdClass $object, string $property): string
    {
        if (!property_exists($object, $property)) {
            return '';
        }
        if (empty($object->$property)) {
            return '';
        }
        return $object->$property;
    }
}
