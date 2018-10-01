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
    public function getAll(string $sortFunction = null): array
    {
        return $this->getAllCombined('all', $sortFunction);
    }

    public function getAllMini(string $sortFunction = null): array
    {
        return $this->getAllCombined('mini', $sortFunction);
    }

    public function getAllShort(): array
    {
        return $this->getAllCombined('short', null);
    }

    private function getAllCombined(string $level, string $sortFunction = null): array
    {
        return $this->wrapArrayCache(
            $this->entityType . 's_' . $level . (!empty($sortFunction) ? '_' . $sortFunction : ''),
            [$this->entityType . 's'],
            function () use ($level, $sortFunction) {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, $this->entityType . '_id');

                switch ($level) {
                    case 'all':
                        $objects = $this->get($ids);
                        break;
                    case 'mini':
                        $objects = $this->getMini($ids);
                        break;
                    case 'short':
                        $objects = $this->getShort($ids);
                        break;
                }

                if (!empty($sortFunction)) {
                    usort($objects, function ($a, $b) use ($sortFunction) {
                        if ($sortFunction == 'getId') {
                            return $a->{$sortFunction}() > $b->{$sortFunction}();
                        }
                        return strcmp($a->{$sortFunction}(), $b->{$sortFunction}());
                    });
                }

                return $objects;
            }
        );
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
        $rawIdentifications = $this->dbs->getIdentifications(self::getIds($entities));
        $identifiers = $this->container->get('identifier_manager')->getWithData($rawIdentifications);
        foreach ($rawIdentifications as $rawIdentification) {
            $entities[$rawIdentification['entity_id']]->addIdentification(
                Identification::constructFromDB(
                    $identifiers[$rawIdentification['identifier_id']],
                    json_decode($rawIdentification['identifications']),
                    json_decode($rawIdentification['authority_ids']),
                    $rawIdentification['identification_extra'],
                    json_decode($rawIdentification['identification_ids'])
                )
            );
        }
    }

    protected function setBibliographies(array &$entities): void
    {
        $rawBibliographies = $this->dbs->getBibliographies(self::getIds($entities));
        if (!empty($rawBibliographies)) {
            $ids = self::getUniqueIds($rawBibliographies, 'reference_id');
            $bibliographies = $this->container->get('bibliography_manager')->get($ids);

            foreach ($rawBibliographies as $rawBibliography) {
                $entities[$rawBibliography['entity_id']]
                    ->addBibliography($bibliographies[$rawBibliography['reference_id']]);
            }
        }
    }

    protected function setInverseBibliographies(array &$entities): void
    {
        $rawInverseBibliographies = $this->dbs->getInverseBibliographies(self::getIds($entities));
        if (!empty($rawInverseBibliographies)) {
            $inverseBibliographies = [];
            foreach (['manuscript', 'occurrence', 'type', 'person'] as $type) {
                $ids = self::getUniqueIds($rawInverseBibliographies, 'entity_id', 'type', $type);
                $inverseBibliographies+= $this->container->get($type . '_manager')->getMini($ids);
            }

            foreach ($rawInverseBibliographies as $rawInverseBibliography) {
                $entities[$rawInverseBibliography['biblio_id']]
                    ->addInverseBibliography(
                        $inverseBibliographies[$rawInverseBibliography['entity_id']],
                        $rawInverseBibliography['type']
                    );
            }

            $biblioIds = self::getUniqueIds($rawInverseBibliographies, 'biblio_id');
            foreach ($biblioIds as $biblioId) {
                $entities[$biblioId]->sortInverseBibliographies();
            }
        }
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

    protected function updateIdentificationwrapper(
        Entity $entity,
        stdClass $data,
        array &$cacheReload,
        string $cacheLevel,
        string $entityType
    ): void {
        $identifiers = $this->container->get('identifier_manager')->getIdentifiersByType($entityType);
        foreach ($identifiers as $identifier) {
            if (property_exists($data, $identifier->getSystemName())
                || property_exists($data, $identifier->getSystemName() . '_extra')
            ) {
                if ((
                    property_exists($data, $identifier->getSystemName())
                    && !is_string($data->{$identifier->getSystemName()})
                ) || (
                    property_exists($data, $identifier->getSystemName() . '_extra')
                    && !is_string($data->{$identifier->getSystemName() . '_extra'})
                ) || ((
                        // identification must exist if an extra is to be set
                        property_exists($data, $identifier->getSystemName() . '_extra')
                        && !empty($data->{$identifier->getSystemName() . '_extra'})
                    ) && (
                        !property_exists($data, $identifier->getSystemName())
                        && empty($entity->getIdentifications()[$identifier->getSystemName()])
                    )
                )
                ) {
                    throw new BadRequestHttpException('Incorrect identification data.');
                }
                $cacheReload[$cacheLevel] = true;
                if (property_exists($data, $identifier->getSystemName())) {
                    $this->updateIdentification(
                        $entity,
                        $identifier,
                        $data->{$identifier->getSystemName()}
                    );
                }
                if (property_exists($data, $identifier->getSystemName() . '_extra')) {
                    $this->updateIdentificationExtra(
                        $entity,
                        $identifier,
                        $data->{$identifier->getSystemName() . '_extra'}
                    );
                }
            }
        }
    }

    protected function updateIdentification(Entity $entity, Identifier $identifier, string $value): void
    {
        if (!empty($value) && !preg_match(
            '~' . $identifier->getRegex() . '~',
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

    protected function updateIdentificationExtra(Entity $entity, Identifier $identifier, string $extra): void
    {
        $this->dbs->beginTransaction();
        try {
            $this->dbs->updateIdentificationExtra($entity->getId(), $identifier->getId(), $extra);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
    }

    protected function updateBibliography(
        Entity $entity,
        stdClass $bibliography,
        bool $referenceTypeRequired = false
    ): void {
        // Verify input
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
                    || ($referenceTypeRequired
                        && (
                            !property_exists($bib, 'referenceType')
                            || !is_object($bib->referenceType)
                            || !property_exists($bib->referenceType, 'id')
                            || !is_numeric($bib->referenceType->id)
                        )
                    )
                    || (property_exists($bib, 'image') && !is_string($bib->image))
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
                    if (!property_exists($bib, 'relUrl') || !(empty($bib->startPage) ||is_string($bib->relUrl))
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                }
            }
        }

        // Add and update
        $oldBibIds = array_keys($entity->getBibliographies());
        $newBibIds = [];
        $this->dbs->beginTransaction();
        try {
            foreach (['article', 'book', 'bookChapter', 'onlineSource'] as $bibType) {
                $plurBibType = $bibType . 's';
                foreach ($bibliography->$plurBibType as $bib) {
                    if (!property_exists($bib, 'id')) {
                        // Add new
                        if (in_array($bibType, ['book', 'article', 'bookChapter'])) {
                            $newBib = $this->container->get('bibliography_manager')->add(
                                $entity->getId(),
                                $bib->{$bibType}->id,
                                self::certainString($bib, 'startPage'),
                                self::certainString($bib, 'endPage'),
                                null,
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $newBibIds[] = $newBib->getId();
                        } else {
                            // onlineSource
                            $newBib = $this->container->get('bibliography_manager')->add(
                                $entity->getId(),
                                $bib->{$bibType}->id,
                                null,
                                null,
                                self::certainString($bib, 'relUrl'),
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $newBibIds[] = $newBib->getId();
                        }
                    } elseif (in_array($bib->id, $oldBibIds)) {
                        $newBibIds[] = $bib->id;
                        // Update
                        if (in_array($bibType, ['book', 'article', 'bookChapter'])) {
                            $this->container->get('bibliography_manager')->update(
                                $bib->id,
                                $bib->{$bibType}->id,
                                self::certainString($bib, 'startPage'),
                                self::certainString($bib, 'endPage'),
                                self::certainString($bib, 'rawPages'),
                                null,
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                        } else {
                            // onlineSource
                            $this->container->get('bibliography_manager')->update(
                                $bib->id,
                                $bib->{$bibType}->id,
                                null,
                                null,
                                null,
                                self::certainString($bib, 'relUrl'),
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                        }
                    } else {
                        throw new NotFoundHttpException(
                            'Bibliography with id "' . $bib->id . '" not found '
                            . ' in entity with id "' . $entity->getId() . '".'
                        );
                    }
                }
            }

            // Reset bibliography item full caches (recalculate inverseBibliographies)
            $addIds = array_diff($newBibIds, $oldBibIds);
            $delIds = array_diff($oldBibIds, $newBibIds);
            $resetIds = array_merge($addIds, $delIds);
            if (count($resetIds) > 0) {
                foreach (['article', 'book', 'book_chapter', 'online_source'] as $bibType) {
                    $bibItems = $this->container->get($bibType . '_manager')->getReferenceDependencies($resetIds);
                    if (count($bibItems) > 0) {
                        array_map(
                            function ($bibItemId) use ($bibType) {
                                $this->container->get($bibType . '_manager')->clearCache($bibItemId, ['full' => true]);
                            },
                            $this->getIds($bibItems)
                        );
                    }
                }
            }

            // delete
            if (count($delIds) > 0) {
                $this->container->get('bibliography_manager')->deleteMultiple($delIds);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // clear article, book, bookchapter, onlinesource caches

            throw $e;
        }
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
