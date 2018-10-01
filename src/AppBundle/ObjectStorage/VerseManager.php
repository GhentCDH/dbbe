<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Model\Verse;

class VerseManager extends ObjectManager
{
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Verse::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $rawVerses = $this->dbs->getBasicInfoByIds($ids);
                return $this->getMiniWithData($rawVerses);
            },
            'verse'
        );
    }

    public function getMiniWithData(array $data): array
    {
        return $this->wrapLevelDataCache(
            Verse::CACHENAME,
            'mini',
            $data,
            'verse_id',
            function ($data) {
                $verses = [];
                foreach ($data as $rawVerse) {
                    $verses[$rawVerse['verse_id']] = new Verse(
                        $rawVerse['verse_id'],
                        $rawVerse['group_id'],
                        $rawVerse['verse'],
                        $rawVerse['order']
                    );
                }

                return $verses;
            },
            'verse'
        );
    }

    public function getShort(array $ids): array
    {
        return $this->wrapLevelCache(
            Verse::CACHENAME,
            'short',
            $ids,
            function ($ids) {
                // Get basic verse information
                $verses = $this->getMini($ids);

                // Remove all ids that did not match above
                $ids = array_keys($verses);

                // offices
                $rawOccMans = $this->dbs->getOccMan($ids);
                $occurrenceIds = self::getUniqueIds($rawOccMans, 'occurrence_id');
                $manuscriptIds = self::getUniqueIds($rawOccMans, 'manuscript_id');
                $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
                $manuscripts = $this->container->get('manuscript_manager')->getMini($manuscriptIds);

                foreach ($rawOccMans as $rawOccMan) {
                    if (!isset($occurrences[$rawOccMan['occurrence_id']])
                        || !isset($manuscripts[$rawOccMan['manuscript_id']])
                    ) {
                        unset($verses[$rawOccMan['verse_id']]);
                    } else {
                        $verses[$rawOccMan['verse_id']]
                            ->setOccurrence($occurrences[$rawOccMan['occurrence_id']])
                            ->setManuscript($manuscripts[$rawOccMan['manuscript_id']]);
                    }
                }

                return $verses;
            },
            'verse'
        );
    }

    /**
     * Get a single verse with all information
     * @param  int        $id
     * @return Verse
     */
    public function getFull(int $id): Verse
    {
        $verses = $this->getShort([$id]);
        if (count($verses) == 0) {
            throw new NotFoundHttpException('Verse with id ' . $id .' not found.');
        }

        return $verses[$id];
    }

    public function getAllShort(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'verse_id');
        return $this->getShort($ids);
    }

    public function getByGroup(int $groupId): array
    {
        $rawIds = $this->dbs->getByGroup($groupId);
        if (count($rawIds) == 0) {
            throw new NotFoundHttpException('Verse variants with id ' . $groupId .' not found.');
        }
        $ids = self::getUniqueIds($rawIds, 'verse_id');
        return $this->getShort($ids);
    }

    public function add(stdClass $data): Verse
    {
        if (!property_exists($data, 'order')
            || !is_numeric($data->order)
            || !property_exists($data, 'verse')
            || !is_string($data->verse)
            || empty($data->verse)
            || !property_exists($data, 'occurrence')
            || !is_object($data->occurrence)
            || !property_exists($data->occurrence, 'id')
            || !is_numeric($data->occurrence->id)
            || empty($data->occurrence->id)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new verse');
        }

        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->order, $data->verse, $data->occurrence->id);

            unset($data->order);
            unset($data->verse);
            unset($data->occurrence);

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    public function update(int $id, stdClass $data, bool $isNew = false)
    {

        $old = $this->getFull($id);
        if ($old == null) {
            throw new NotFoundHttpException('Verse with id ' . $id .' not found.');
        }

        $cacheReload = [
            'mini' => $isNew,
        ];

        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'order')) {
                // Order is a required field
                if (!is_numeric($data->order)) {
                    throw new BadRequestHttpException('Incorrect order data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateOrder($id, $data->order);
            }
            if (property_exists($data, 'verse')) {
                // Verse is a required field
                if (!is_string($data->verse) || empty($data->verse)) {
                    throw new BadRequestHttpException('Incorrect verse data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateVerse($id, $data->verse);
            }
            // occurrence cannot be updated
            // groupId can be updated
            // * by groupId (caused by unlinking or the update of another verse)
            // * by linkVerses
            if (property_exists($data, 'groupId')) {
                if (is_numeric($data->groupId) || empty($data->groupId)) {
                    $cacheReload['mini'] = true;
                    $this->dbs->updateGroup($id, $data->groupId);
                }
            }
            if (property_exists($data, 'linkVerses')) {
                if (!is_array($data->linkVerses)
                ) {
                    throw new BadRequestHttpException('Incorrect linkVerses data.');
                }
                $cacheReload['mini'] = true;
                if (empty($data->linkVerses)) {
                    // Create a group for this single verse
                    $this->updateNewGroupFromVerses($id);
                } else {
                    // Group all verses together
                    $this->updateGroupFromVerses($old, $data->linkVerses);
                }
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

    private function updateNewGroupFromVerses($id)
    {
        $this->dbs->beginTransaction();
        try {
            $groupId = $this->dbs->getGroupId();
            $this->dbs->updateGroup($id, $groupId);
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // resets are handled by outer try catch

            throw $e;
        }
    }

    private function updateGroupFromVerses(Verse $old, array $linkVerses)
    {
        // Sanitize
        foreach ($linkVerses as $linkVerse) {
            if (!is_object($linkVerse)
                ||!property_exists($linkVerse, 'id')
                ||!is_numeric($linkVerse->id)
                ||(
                    property_exists($linkVerse, 'groupId')
                    && !(is_numeric($linkVerse->groupId) || empty($linkVerse->groupId))
                )
            ) {
                throw new BadRequestHttpException('Incorrect linkVerses data.');
            }
        }

        // find min groupId (can be null)
        $groupId = $old->getGroupId();
        foreach ($linkVerses as $linkVerse) {
            if (property_exists($linkVerse, 'group_id')
                && !empty($linkVerse->group_id)
                && ($groupId == null || $linkVerse->group_id < $groupId)
            ) {
                $groupId = $linkVerse->group_id;
            }
        }

        $this->dbs->beginTransaction();
        try {
            if ($groupId == null) {
                $groupId = $this->dbs->getGroupId();
            }

            $this->dbs->updateGroup($old->getId(), $groupId);

            foreach ($linkVerses as $linkVerse) {
                if (!property_exists($linkVerse, 'group_id')
                    || $linkVerse->group_id != $groupId
                ) {
                    $this->update($linkVerse->id, json_decode(json_encode(['groupId' => $groupId])));
                }
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // resets are handled by outer try catch

            throw $e;
        }
    }

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

            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return;
    }
}
