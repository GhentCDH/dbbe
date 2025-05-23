<?php

namespace App\ObjectStorage;

use App\Model\Url;
use DateTime;
use Elastica\Processor\Date;
use Exception;
use stdClass;

use App\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Model\Entity;
use App\Model\Identification;
use App\Model\Identifier;

abstract class EntityManager extends ObjectManager
{
    abstract protected function getAllCombined(string $level, string $sortFunction = null): array;

    /**
     * Get entities with enough information for the sitemap: id and modification date
     * @param  array $ids
     * @return array
     */
    public function getSitemap(array $ids): array
    {
        $entities = [];
        foreach ($ids as $id) {
            $entities[$id] = (new Entity())
                ->setId($id);
        }

        $this->setCreatedAndModifiedDates($entities);

        return $entities;
    }

    /**
     * Get the last modification timestamp of an entity of a specific type
     * @return DateTime
     */
    public function getLastModified(): DateTime
    {
        return new DateTime($this->dbs->getLastModified()['modified']);
    }

    public function getAllShortJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedShortJson('all', $sortFunction);
    }

    public function getAllJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedJson('all', $sortFunction);
    }

    protected function getAllCombinedShortJson(string $level, string $sortFunction = null): array
    {
        return ArrayToJson::arrayToShortJson($this->getAllCombined($level, $sortFunction));
    }

    protected function getAllCombinedJson(string $level, string $sortFunction = null): array
    {
        return ArrayToJson::arrayToJson($this->getAllCombined($level, $sortFunction));
    }

    public function getArticleDependencies(int $articleId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByArticleId($articleId), $method);
    }

    public function getBlogPostDependencies(int $blogPostId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBlogPostId($blogPostId), $method);
    }

    public function getBookDependencies(int $bookId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookId($bookId), $method);
    }

    public function getBookChapterDependencies(int $bookChapterId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookChapterId($bookChapterId), $method);
    }

    public function getOnlineSourceDependencies(int $onlineSourceId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOnlineSourceId($onlineSourceId), $method);
    }

    public function getPhdDependencies(int $phdId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPhdId($phdId), $method);
    }

    public function getBibVariaDependencies(int $bibVariaId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBibVariaId($bibVariaId), $method);
    }

    public function getManagementDependencies(int $managementId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByManagementId($managementId), $method);
    }

    public function getReferenceDependencies(array $referenceIds): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByReferenceIds($referenceIds), 'getMini');
    }

    protected function setPublics(array &$entities): void
    {
        $rawPublics = $this->dbs->getPublics(array_keys($entities));
        foreach ($rawPublics as $rawPublic) {
            $entities[$rawPublic['entity_id']]
                // default: true (if no value is set in the database)
                ->setPublic(isset($rawPublic['public']) ? $rawPublic['public'] : true);
        }
    }

    protected function setCreatedAndModifiedDates(array &$entities): void
    {
        $rawCreatedAndModifiedDates = $this->dbs->getCreatedAndModifiedDates(array_keys($entities));
        foreach ($rawCreatedAndModifiedDates as $rawCreatedAndModifiedDate) {
            $entities[$rawCreatedAndModifiedDate['entity_id']]
                // default: true (if no value is set in the database)
                ->setCreated(new DateTime($rawCreatedAndModifiedDate['created']))
                ->setModified(new DateTime($rawCreatedAndModifiedDate['modified']));
        }
    }

    protected function setComments(array &$entities): void
    {
        $rawComments = $this->dbs->getComments(array_keys($entities));
        foreach ($rawComments as $rawComment) {
            $entities[$rawComment['entity_id']]
                ->setPublicComment($rawComment['public_comment'])
                ->setPrivateComment($rawComment['private_comment']);
        }
    }

    protected function setIdentifications(array &$entities): void
    {
        $rawIdentifications = $this->dbs->getIdentifications(array_keys($entities));
        $identifiers = $this->container->get(IdentifierManager::class)->getWithData($rawIdentifications);
        foreach ($rawIdentifications as $rawIdentification) {
            $entities[$rawIdentification['entity_id']]->addIdentifications(
                Identification::constructFromDB(
                    $identifiers[$rawIdentification['identifier_id']],
                    json_decode($rawIdentification['identifications']),
                    json_decode($rawIdentification['identification_volumes']),
                    json_decode($rawIdentification['identification_extras'])
                )
            );
        }
        $identifierLookup = [];
        foreach ($entities as $entity) {
            // Add all identifiers, so they can be used in elasticsearch
            if (!array_key_exists($entity::CACHENAME, $identifierLookup)) {
                $identifierLookup[$entity::CACHENAME] = $this->container->get(IdentifierManager::class)->getByType($entity::CACHENAME);
            }
            $allIdentifiers = $identifierLookup[$entity::CACHENAME];
            foreach ($allIdentifiers as $identifier) {
                $present = false;
                foreach (array_keys($entity->getIdentifications()) as $identifier_name) {
                    if ($identifier_name == $identifier->getSystemName()) {
                        $present = true;
                        break;
                    }
                }
                if (!$present) {
                    $entity->addIdentifications(Identification::constructFromDB($identifier, [], [], []));
                }
            }
            // Sort identifications
            $entity->sortIdentifications();
        }
    }

    protected function setInverseIdentifications(array &$entities): void
    {
        $rawInverseIdentifications = $this->dbs->getInverseIdentifications(array_keys($entities));
        if (!empty($rawInverseIdentifications)) {
            $inverseIdentifications = [];
            foreach (ElasticManagers::MANAGERS as $managerType => $manager) {
                $ids = self::getUniqueIds($rawInverseIdentifications, 'entity_id', 'type', $managerType);
                $inverseIdentifications += $this->container->get($manager)->getMini($ids);
            }
            // Regions
            $ids = self::getUniqueIds($rawInverseIdentifications, 'entity_id', 'type', 'region');
            $inverseIdentifications += $this->container->get(RegionManager::class)->getWithParents($ids);

            foreach ($rawInverseIdentifications as $rawInverseIdentification) {
                $entities[$rawInverseIdentification['identifier_id']]
                    ->addInverseIdentification(
                        $inverseIdentifications[$rawInverseIdentification['entity_id']],
                        $rawInverseIdentification['type']
                    );
            }
        }
    }

    protected function setBibliographies(array &$entities): void
    {
        $rawBibliographies = $this->dbs->getBibliographies(array_keys($entities));
        if (!empty($rawBibliographies)) {
            $ids = self::getUniqueIds($rawBibliographies, 'reference_id');
            $bibliographies = $this->container->get(BibliographyManager::class)->get($ids);

            foreach ($rawBibliographies as $rawBibliography) {
                $entities[$rawBibliography['entity_id']]
                    ->addBibliography($bibliographies[$rawBibliography['reference_id']]);
            }
        }
    }

    protected function setInverseBibliographies(array &$entities): void
    {
        $rawInverseBibliographies = $this->dbs->getInverseBibliographies(array_keys($entities));
        if (!empty($rawInverseBibliographies)) {
            $inverseBibliographies = [];
            foreach (['manuscript', 'occurrence', 'type', 'person'] as $entityType) {
                $ids = self::getUniqueIds($rawInverseBibliographies, 'entity_id', 'type', $entityType);
                $inverseBibliographies += $this->container->get(ElasticManagers::MANAGERS[$entityType])->getMini($ids);
            }
            // Add linked type instead of translation
            $translationIds = self::getUniqueIds($rawInverseBibliographies, 'entity_id', 'type', 'translation');
            foreach ($translationIds as $translationId) {
                $types = $this->container->get(TypeManager::class)->getTranslationDependencies($translationId, 'getMini');
                $inverseBibliographies[$translationId] = reset($types);
            }

            foreach ($rawInverseBibliographies as $rawInverseBibliography) {
                $entities[$rawInverseBibliography['biblio_id']]
                    ->addInverseBibliography(
                        $inverseBibliographies[$rawInverseBibliography['entity_id']],
                        $rawInverseBibliography['type']
                    );
            }
        }
    }

    protected function setManagements(array &$entities): void
    {
        $rawManagements = $this->dbs->getManagements(array_keys($entities));
        if (!empty($rawManagements)) {
            $managements = $this->container->get(ManagementManager::class)->getWithData($rawManagements);

            foreach ($rawManagements as $rawManagement) {
                $entities[$rawManagement['entity_id']]
                    ->addManagement($managements[$rawManagement['management_id']]);
            }
        }
    }

    protected function setUrls(array &$entities): void
    {
        $rawUrls = $this->dbs->getUrls(array_keys($entities));
        foreach ($rawUrls as $rawUrl) {
            $entities[$rawUrl['entity_id']]
                ->addUrl(new Url($rawUrl['url_id'], $rawUrl['url'], $rawUrl['title']));
        }
    }

    protected function updatePublic(Entity $entity, bool $public): void
    {
        $this->dbs->updatePublic($entity->getId(), $public);
    }

    private function fixDBNegativeDate(string $date)
    {
        if (substr($date, 0, 1) == '-') {
            return substr($date, 1) . ' BC';
        }
        return $date;
    }

    protected function getDBDate(stdClass $date)
    {
        return '('
            . (empty($date->floor) ? '-infinity' : self::fixDBNegativeDate($date->floor))
            . ', '
            . (empty($date->ceiling) ? 'infinity' : self::fixDBNegativeDate($date->ceiling))
            . ')';
    }

    protected function getDBInterval(stdClass $date)
    {
        return '('
            . (empty($date->start->floor) ? '-infinity' : self::fixDBNegativeDate($date->start->floor))
            . ', '
            . (empty($date->start->ceiling) ? 'infinity' : self::fixDBNegativeDate($date->start->ceiling))
            . ', '
            . (empty($date->end->floor) ? '-infinity' : self::fixDBNegativeDate($date->end->floor))
            . ', '
            . (empty($date->end->ceiling) ? 'infinity' : self::fixDBNegativeDate($date->end->ceiling))
            . ')';
    }

    protected function validateDates($dates): void
    {
        if (!is_array($dates)) {
            throw new BadRequestHttpException('Incorrect dates data.');
        }
        foreach ($dates as $index => $item) {
            if (
                !is_object($item)
                || !property_exists($item, 'type')
                || !is_string($item->type)
                || empty($item->type)
                || !property_exists($item, 'isInterval')
                || !is_bool($item->isInterval)
                || (!$item->isInterval
                    && (!property_exists($item, 'date')
                        || !is_object($item->date)
                        || !property_exists($item->date, 'floor')
                        || (!is_string($item->date->floor) && !is_null($item->date->floor))
                        || !property_exists($item->date, 'ceiling')
                        || (!is_string($item->date->ceiling) && !is_null($item->date->ceiling))
                    )
                )
                || ($item->isInterval
                    && (!property_exists($item, 'interval')
                        || !is_object($item->interval)
                        || !property_exists($item->interval, 'start')
                        || !is_object($item->interval->start)
                        || !property_exists($item->interval->start, 'floor')
                        || (!is_string($item->interval->start->floor) && !is_null($item->interval->start->floor))
                        || !property_exists($item->interval->start, 'ceiling')
                        || (!is_string($item->interval->start->ceiling) && !is_null($item->interval->start->ceiling))
                        || !property_exists($item->interval, 'end')
                        || !is_object($item->interval->end)
                        || !property_exists($item->interval->end, 'floor')
                        || (!is_string($item->interval->end->floor) && !is_null($item->interval->end->floor))
                        || !property_exists($item->interval->end, 'ceiling')
                        || (!is_string($item->interval->end->ceiling) && !is_null($item->interval->end->ceiling))
                    )
                )
            ) {
                throw new BadRequestHttpException('Incorrect date or interval in dates data (' . $index . ').');
            }
            // date check
            if (!$item->isInterval) {
                $this->validateDate($item->date->floor, $index);
                $this->validateDate($item->date->ceiling, $index);
            } else {
                $this->validateDate($item->interval->start->floor, $index);
                $this->validateDate($item->interval->start->ceiling, $index);
                $this->validateDate($item->interval->end->floor, $index);
                $this->validateDate($item->interval->end->ceiling, $index);
            }
        }
    }

    private function validateDate($input, $index)
    {
        if (is_string($input)) {
            try {
                new DateTime($input);
            } catch (Exception $e) {
                throw new BadRequestHttpException('Invalid date or interval date in dates data (' . $index . ').');
            }
        }
    }

    protected function updateUrlswrapper(
        Entity $entity,
        stdClass $data,
        array &$changes,
        string $level
    ): void {
        if (property_exists($data, 'urls')) {
            if ($data->urls == null) {
                $data->urls = [];
            }
            if (!is_array($data->urls)) {
                throw new BadRequestHttpException('Incorrect urls data.');
            }
            foreach ($data->urls as $url) {
                if (
                    !is_object($url)
                    || !property_exists($url, 'url')
                    || !is_string($url->url)
                    || (property_exists($url, 'title')
                        && !is_string($url->title)
                        && !empty($url->title)
                    )
                    || (property_exists($url, 'id')
                        && !is_numeric($url->id)
                        && !empty($url->id)
                    )
                ) {
                    throw new BadRequestHttpException('Incorrect urls data.');
                }
            }
            $changes[$level] = true;
            $oldUrls = $entity->getUrls() ?? [];
            $addUrls = [];
            $updateUrls = [];
            $keepUrlIds = [];
            $delUrlIds = [];
            foreach ($data->urls as $newIndex => $newUrl) {
                if (property_exists($newUrl, 'id') && $newUrl->id != null) {
                    $found = false;
                    foreach ($oldUrls as $oldIndex => $oldUrl) {
                        if ($oldUrl->getId() === $newUrl->id) {
                            $found = true;
                            if (
                                $oldIndex === $newIndex
                                && $oldUrl->getUrl() == $newUrl->url
                                && (
                                    ($oldUrl->getTitle() == null
                                        && (!property_exists($newUrl, 'title')) || $newUrl->title == null
                                    )
                                    || (property_exists($newUrl, 'title')
                                        && $oldUrl->getTitle() == $newUrl->title
                                    )
                                )
                            ) {
                                $keepUrlIds[] = $newUrl->id;
                            } else {
                                $updateUrls[] = [$newUrl, $newIndex + 1];
                            }
                            break;
                        }
                    }
                    if (!$found) {
                        throw new BadRequestHttpException('Incorrect urls data.');
                    }
                } else {
                    $addUrls[] = [$newUrl, $newIndex + 1];
                }
            }
            foreach ($oldUrls as $oldUrl) {
                $oldId = $oldUrl->getId();
                $found = false;
                foreach ($keepUrlIds as $keepUrlId) {
                    if ($keepUrlId === $oldId) {
                        $found = true;
                    }
                }
                foreach ($updateUrls as $updateUrl) {
                    if ($updateUrl[0]->id === $oldId) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $delUrlIds[] = $oldId;
                }
            }
            foreach ($updateUrls as $updateUrl) {
                $this->dbs->updateEntityUrl(
                    $updateUrl[0]->id,
                    $updateUrl[0]->url,
                    $updateUrl[1],
                    property_exists($updateUrl[0], 'title') ? $updateUrl[0]->title : null
                );
            }
            foreach ($addUrls as $addUrl) {
                $this->dbs->addEntityUrl(
                    $entity->getId(),
                    $addUrl[0]->url,
                    $addUrl[1],
                    property_exists($addUrl[0], 'title') ? $addUrl[0]->title : null
                );
            }
            if (count($delUrlIds) > 0) {
                $this->dbs->delEntityUrls($delUrlIds);
            }
        }
    }

    protected function updateIdentificationwrapper(
        Entity $entity,
        stdClass $data,
        array &$changes,
        string $level,
        string $entityType
    ): void {
        if (property_exists($data, 'identification')) {
            if (!is_object($data->identification)) {
                throw new BadRequestHttpException('Incorrect identification data.');
            }
            $identifiers = $this->container->get(IdentifierManager::class)->getByType($entityType);
            foreach ($identifiers as $identifier) {
                if (property_exists($data->identification, $identifier->getSystemName())) {
                    if (
                        !empty($data->identification->{$identifier->getSystemName()})
                        && !is_array($data->identification->{$identifier->getSystemName()})
                    ) {
                        throw new BadRequestHttpException('Incorrect identification data.');
                    }
                    if (!empty($data->identification->{$identifier->getSystemName()})) {
                        foreach ($data->identification->{$identifier->getSystemName()} as $identification) {
                            if (
                                !property_exists($identification, 'identification')
                                || empty($identification->identification)
                                || !is_string($identification->identification)
                                || !preg_match('~' . $identifier->getRegex() . '~', $identification->identification)
                            ) {
                                throw new BadRequestHttpException('Incorrect identification identification data.');
                            }
                            if ($identifier->getVolumes() > 1) {
                                if (
                                    !property_exists($identification, 'volume')
                                    || !is_numeric($identification->volume)
                                ) {
                                    throw new BadRequestHttpException('Incorrect identification volume data.');
                                }
                            }
                            if ($identifier->getExtraRequired()) {
                                if (
                                    !property_exists($identification, 'extra')
                                    || empty($identification->extra)
                                ) {
                                    throw new BadRequestHttpException('Identification extra is required.');
                                }
                            }
                            if ($identifier->getExtra()) {
                                if (
                                    property_exists($identification, 'extra')
                                    && !empty($identification->extra)
                                    && !is_string($identification->extra)
                                ) {
                                    throw new BadRequestHttpException('Incorrect identification extra data.');
                                }
                            }
                        }
                    }
                }
            }
            $changes[$level] = true;
            foreach ($identifiers as $identifier) {
                if (
                    property_exists($data->identification, $identifier->getSystemName())
                    || array_key_exists($identifier->getSystemName(), $entity->getIdentifications())
                ) {
                    $oldIdentifications = isset($entity->getIdentifications()[$identifier->getSystemName()]) ? $entity->getIdentifications()[$identifier->getSystemName()][1] : [];
                    $newIdentifications = property_exists($data->identification, $identifier->getSystemName()) && $data->identification->{$identifier->getSystemName()} != null ? $data->identification->{$identifier->getSystemName()} : [];

                    if ($identifier->getVolumes() == 1) {
                        $this->updateIdentification(
                            $entity,
                            $identifier,
                            $oldIdentifications,
                            $newIdentifications
                        );
                    } else {
                        $oldVolumes = array_unique(array_map(function ($oldIdentification) {
                            return $oldIdentification->getVolume();
                        }, $oldIdentifications));
                        $newVolumes = array_unique(array_map(function ($newIdentification) {
                            return $newIdentification->volume;
                        }, $newIdentifications));
                        $allVolumes = array_merge($oldVolumes, $newVolumes);

                        foreach ($allVolumes as $volume) {
                            $this->updateIdentification(
                                $entity,
                                $identifier,
                                array_filter($oldIdentifications, function ($oldIdentification) use ($volume) {
                                    return $oldIdentification->getVolume() == $volume;
                                }),
                                array_filter($newIdentifications, function ($newIdentification) use ($volume) {
                                    return $newIdentification->volume == $volume;
                                }),
                                $volume
                            );
                        }
                    }
                }
            }
        }
    }

    protected function updateIdentification(Entity $entity, Identifier $identifier, array $old, array $new = null, int $volume = null): void
    {
        // No old and no new value => do nothing
        if (empty($old) && empty($new)) {
            return;
        }
        // Old value, but no new value => delete
        elseif (!empty($old) && empty($new)) {
            $this->dbs->delIdentification($entity->getId(), $identifier->getId(), $volume);
            // Insert or update
        } else {
            $identificationValue = implode('|', array_map(function ($identification) {
                return $identification->identification;
            }, $new));
            $extraValue = null;
            if ($identifier->getExtra()) {
                $extraValue = implode('|', array_map(function ($identification) {
                    return $identification->extra;
                }, $new));
            } // Insert
            if (empty($old)) {
                $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), $identificationValue, $extraValue, $volume);
            }
            // Update if necessary
            else {
                $oldExtraValue = null;
                $oldIdentificationValue = implode('|', array_map(function ($oldIdentification) {
                    return $oldIdentification->getIdentification();
                }, $old));
                if ($identifier->getExtra()) {
                    $oldExtraValue = implode('|', array_map(function ($oldIdentification) {
                        return $oldIdentification->getExtra();
                    }, $old));
                }
                if ($oldIdentificationValue !== $identificationValue || $oldExtraValue !== $extraValue) {
                    $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), $identificationValue, $extraValue, $volume);
                }
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

    private function updateOnlineSourceAccess($id): void {
        //TODO: Cleaner fix.
        $dbbeOnlineSourceId = 11523;
        //Only update DBBE if the altered source is NOT DBBE so that manual lastModified changes are possible
        if ($id !== $dbbeOnlineSourceId) {
            //Update last accessed date of the source that has been changed
            $this->container->get(OnlineSourceManager::class)->updateLastAccessed(
                $id,
                (new \DateTime())->format('Y-m-d')
            );
            //Use this occasion to update DBBE to a recent last accessed
            $this->container->get(OnlineSourceManager::class)->updateLastAccessed(
                $dbbeOnlineSourceId,
                (new \DateTime())->format('Y-m-d')
            );
        }
    }

    protected function updateBibliography(
        Entity $entity,
        stdClass $bibliography,
        bool $referenceTypeRequired = false
    ): void {
        // Verify input
        foreach (['book', 'article', 'bookChapter', 'onlineSource', 'blogPost', 'phd', 'bibVaria'] as $bibType) {
            $plurBibType = $bibType . 's';
            if (!property_exists($bibliography, $plurBibType) || !is_array($bibliography->$plurBibType)) {
                throw new BadRequestHttpException('Incorrect bibliography data.');
            }
            foreach ($bibliography->$plurBibType as $bib) {
                if (
                    !is_object($bib)
                    || (property_exists($bib, 'id') && (empty($bib->id) || !is_numeric($bib->id)))
                    || !property_exists($bib, $bibType) || !is_object($bib->$bibType)
                    || !property_exists($bib->$bibType, 'id') || !is_numeric($bib->$bibType->id)
                    || ($referenceTypeRequired
                        && (!property_exists($bib, 'referenceType')
                            || !is_object($bib->referenceType)
                            || !property_exists($bib->referenceType, 'id')
                            || !is_numeric($bib->referenceType->id)
                        )
                    )
                    || (property_exists($bib, 'image') && !(is_string($bib->image) || is_null($bib->image)))
                ) {
                    throw new BadRequestHttpException('Incorrect bibliography data.' . json_encode($bib));
                }
                if (in_array($bibType, ['book', 'article', 'bookChapter', 'phd', 'bibVaria'])) {
                    if (
                        !property_exists($bib, 'startPage') || !(empty($bib->startPage) || is_string($bib->startPage))
                        || !property_exists($bib, 'endPage')  || !(empty($bib->endPage) || is_string($bib->endPage))
                        || (property_exists($bib, 'rawPages') && !(empty($bib->rawPages) || is_string($bib->rawPages)))
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                } elseif (in_array($bibType, ['onlineSource'])) {
                    if (
                        !property_exists($bib, 'relUrl') || !(empty($bib->relUrl) || is_string($bib->relUrl))
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
            foreach (['article', 'book', 'bookChapter', 'onlineSource', 'blogPost', 'phd', 'bibVaria'] as $bibType) {
                $plurBibType = $bibType . 's';
                foreach ($bibliography->$plurBibType as $bib) {
                    if (!property_exists($bib, 'id')) {
                        // Add new
                        if (in_array($bibType, ['book', 'article', 'bookChapter', 'phd', 'bibVaria'])) {
                            $newBib = $this->container->get(BibliographyManager::class)->add(
                                $entity->getId(),
                                $bib->{$bibType}->id,
                                self::certainString($bib, 'startPage'),
                                self::certainString($bib, 'endPage'),
                                null,
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $newBibIds[] = $newBib->getId();
                        } elseif (in_array($bibType, ['onlineSource'])) {
                            $newBib = $this->container->get(BibliographyManager::class)->add(
                                $entity->getId(),
                                $bib->{$bibType}->id,
                                null,
                                null,
                                self::certainString($bib, 'relUrl'),
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $newBibIds[] = $newBib->getId();
                            $this->updateOnlineSourceAccess($bib->{$bibType}->id);
                        } elseif (in_array($bibType, ['blogPost'])) {
                            $newBib = $this->container->get(BibliographyManager::class)->add(
                                $entity->getId(),
                                $bib->{$bibType}->id,
                                null,
                                null,
                                null,
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $newBibIds[] = $newBib->getId();
                        }
                    } elseif (in_array($bib->id, $oldBibIds)) {
                        $newBibIds[] = $bib->id;
                        // Update
                        if (in_array($bibType, ['book', 'article', 'bookChapter', 'phd', 'bibVaria'])) {
                            $this->container->get(BibliographyManager::class)->update(
                                $bib->id,
                                $bib->{$bibType}->id,
                                self::certainString($bib, 'startPage'),
                                self::certainString($bib, 'endPage'),
                                self::certainString($bib, 'rawPages'),
                                null,
                                property_exists($bib, 'referenceType') && $bib->referenceType != null ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                        } elseif (in_array($bibType, ['onlineSource'])) {
                            $this->container->get(BibliographyManager::class)->update(
                                $bib->id,
                                $bib->{$bibType}->id,
                                null,
                                null,
                                null,
                                self::certainString($bib, 'relUrl'),
                                property_exists($bib, 'referenceType') ? $bib->referenceType->id : null,
                                property_exists($bib, 'image') ? $bib->image : null
                            );
                            $this->updateOnlineSourceAccess($bib->{$bibType}->id);                       }
                        elseif (in_array($bibType, ['blogPost'])) {
                            $this->container->get(BibliographyManager::class)->update(
                                $bib->id,
                                $bib->{$bibType}->id,
                                null,
                                null,
                                null,
                                null,
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

            // delete
            $delIds = array_diff($oldBibIds, $newBibIds);
            if (count($delIds) > 0) {
                $this->container->get(BibliographyManager::class)->deleteMultiple($delIds);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            throw $e;
        }
    }

    protected function updateManagementWrapper(
        Entity $entity,
        stdClass $data,
        array &$changes,
        string $level
    ): void {
        if (property_exists($data, 'managements')) {
            if (!is_array($data->managements)) {
                throw new BadRequestHttpException('Incorrect managements data.');
            }
            foreach ($data->managements as $management) {
                if (
                    !is_object($management)
                    || !property_exists($management, 'id')
                    || !is_numeric($management->id)
                ) {
                    throw new BadRequestHttpException('Incorrect management data.');
                }
            }
            $changes[$level] = true;

            list($delIds, $addIds) = self::calcDiff($data->managements, $entity->getManagements());

            if (count($delIds) > 0) {
                $this->dbs->delManagements($entity->getId(), $delIds);
            }
            foreach ($addIds as $addId) {
                $this->dbs->addManagement($entity->getId(), $addId);
            }
        }
    }

    public function updateElasticManagement(array $ids): void
    {
        if (!empty($ids)) {
            $rawManagements = $this->dbs->getManagements($ids);
            if (!empty($rawManagements)) {
                $managements = $this->container->get(ManagementManager::class)->getWithData($rawManagements);
                $data = [];

                foreach ($rawManagements as $rawManagement) {
                    if (!isset($data[$rawManagement['entity_id']])) {
                        $data[$rawManagement['entity_id']] = [
                            'id' => $rawManagement['entity_id'],
                            'management' => [],
                        ];
                    }
                    $data[$rawManagement['entity_id']]['management'][] =
                        $managements[$rawManagement['management_id']]->getShortJson();
                }

                $this->ess->updateMultiple($data);
            }
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
