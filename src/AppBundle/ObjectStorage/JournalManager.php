<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Journal;

/**
 * ObjectManager for journals
 * Servicename: journal_manager
 */
class JournalManager extends DocumentManager
{
    /**
     * Get journals with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $journals = [];
        $rawJournals = $this->dbs->getJournalsByIds($ids);

        foreach ($rawJournals as $rawJournal) {
            $journals[$rawJournal['journal_id']] = new Journal(
                $rawJournal['journal_id'],
                $rawJournal['title']
            );
        }

        return $journals;
    }

    /**
     * Get all journals with minimal information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllShortJson(string $sortFunction = null): array
    {
        return parent::getAllShortJson($sortFunction == null ? 'getTitle' : $sortFunction);
    }

    /**
     * Get all journals with all information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllJson(string $sortFunction = null): array
    {
        return parent::getAllJson($sortFunction == null ? 'getTitle' : $sortFunction);
    }

    /**
     * Add a new journal
     * @param  stdClass $data
     * @return Journal
     */
    public function add(stdClass $data): Journal
    {
        if (# mandatory
            !property_exists($data, 'title')
            || !is_string($data->title)
            || empty($data->title)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new journal');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->title);

            $new = $this->get([$id])[$id];

            $this->updateModified(null, $new);

            $this->cache->invalidateTags(['journals']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing journal
     * @param  int      $id
     * @param  stdClass $data
     * @return Journal
     */
    public function update(int $id, stdClass $data): Journal
    {
        $journals = $this->get([$id]);
        if (count($journals) == 0) {
            throw new NotFoundHttpException('Journal with id ' . $id .' not found.');
        }

        $this->dbs->beginTransaction();
        try {
            $correct = false;
            if (property_exists($data, 'title')
                && is_string($data->title)
                && !empty($data->title)
            ) {
                $correct = true;
                $this->dbs->updateTitle($id, $data->title);
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified($journals[$id], $new);

            $this->cache->invalidateTags(['journals']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Merge two journals
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return Journal
     */
    public function merge(int $primaryId, int $secondaryId): Journal
    {
        $journals = $this->get([$primaryId, $secondaryId]);
        if (count($journals) != 2) {
            if (!array_key_exists($primaryId, $journals)) {
                throw new NotFoundHttpException('Journal with id ' . $primaryId .' not found.');
            }
            if (!array_key_exists($secondaryId, $journals)) {
                throw new NotFoundHttpException('Journal with id ' . $secondaryId .' not found.');
            }
            throw new BadRequestHttpException(
                'Journals with id ' . $primaryId .' and id ' . $secondaryId . ' cannot be merged.'
            );
        }
        $primary = $journals[$primaryId];

        $journalIssues = $this->container->get('journal_issue_manager')->getJournalDependencies($secondaryId, 'get');

        $this->dbs->beginTransaction();
        try {
            if (!empty($journalIssues)) {
                foreach ($journalIssues as $journalIssue) {
                    $this->container->get('journal_issue_manager')->update(
                        $journalIssue->getId(),
                        json_decode(json_encode([
                            'journal' => [
                                'id' => $primaryId,
                            ],
                        ]))
                    );
                }
            }
            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            throw $e;
        }

        return $primary;
    }

    /**
     * Delete a journal
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $journals = $this->get([$id]);
            if (count($journals) == 0) {
                throw new NotFoundHttpException('Journal with id ' . $id .' not found.');
            }
            $journal = $journals[$id];

            $this->dbs->delete($id);

            $this->cache->invalidateTags(['journals']);

            $this->updateModified($journal, null);

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
