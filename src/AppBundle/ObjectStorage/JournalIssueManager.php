<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\JournalIssue;

/**
 * ObjectManager for journal issues
 * Servicename: journal_issue_manager
 */
class JournalIssueManager extends DocumentManager
{
    /**
     * Get journal issues with all information
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $journalIssues = [];
        $rawJournalIssues = $this->dbs->getJournalIssuesByIds($ids);

        $journalIds = self::getUniqueIds($rawJournalIssues, 'journal_id');
        $journals = $this->container->get('journal_manager')->getMini($journalIds);

        foreach ($rawJournalIssues as $rawJournalIssue) {
            $journalIssues[$rawJournalIssue['journal_issue_id']] = new JournalIssue(
                $rawJournalIssue['journal_issue_id'],
                $journals[$rawJournalIssue['journal_id']],
                $rawJournalIssue['year'],
                $rawJournalIssue['volume'],
                $rawJournalIssue['number']
            );
        }

        return $journalIssues;
    }

    public function getShort(array $ids): array
    {
        return $this->getMini($ids);
    }

    public function getFull(int $id): JournalIssue
    {
        $journalIssues = $this->getShort([$id]);
        if (count($journalIssues) == 0) {
            throw new NotFoundHttpException('Journal issue with id ' . $id .' not found.');
        }
        return $journalIssues[$id];
    }

    /**
     * Get all journal issues with minimal information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return parent::getAllMiniShortJson($sortFunction == null ? 'getDescription' : $sortFunction);
    }

    /**
     * Get all journal issues with all information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */

    public function getAllJson(string $sortFunction = null): array
    {
        return $this->getAllCombinedJson('short', $sortFunction);
    }

    /**
     * Get all journal issues that are dependent on a specific journal
     * @param  int   $journalId
     * @return array
     */
    public function getJournalDependencies(int $journalId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByJournalId($journalId), 'getMini');
    }

    /**
     * Add a new journal issue
     * @param  stdClass $data
     * @return JournalIssue
     */
    public function add(stdClass $data): JournalIssue
    {
        if (# mandatory
            !property_exists($data, 'journal')
            || !is_object($data->journal)
            || empty($data->journal)
            || !property_exists($data->journal, 'id')
            || !is_numeric($data->journal->id)
            || empty($data->journal->id)
            || !property_exists($data, 'year')
            || !is_numeric($data->year)
            || empty($data->year)
            # optional
            || (property_exists($data, 'volume') && !is_numeric($data->volume))
            || (property_exists($data, 'number') && !is_numeric($data->volume))
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new journal');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->journal->id, $data->year, $data->volume, $data->number);

            $new = $this->getFull($id);

            $this->updateModified(null, $new);

            $this->cache->invalidateTags(['journal_issues']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing journal issue
     * @param  int      $id
     * @param  stdClass $data
     * @return JournalIssue
     */
    public function update(int $id, stdClass $data): JournalIssue
    {
        // Throws NotFoundException if not found
        $old = $this->getFull($id);

        $this->dbs->beginTransaction();
        try {
            $correct = false;
            if (property_exists($data, 'journal')
                && is_object($data->journal)
                && !empty($data->journal)
                && property_exists($data->journal, 'id')
                && is_numeric($data->journal->id)
                && !empty($data->journal->id)
            ) {
                $correct = true;
                $this->dbs->updateJournal($id, $data->journal->id);
            }
            if (property_exists($data, 'year')
                && is_numeric($data->year)
                && !empty($data->year)
            ) {
                $correct = true;
                $this->dbs->updateYear($id, $data->year);
            }
            if (property_exists($data, 'volume')
                && (is_numeric($data->volume) || empty($data->volume))
            ) {
                $correct = true;
                $this->dbs->updateVolume($id, $data->volume);
            }
            if (property_exists($data, 'number')
                && (is_numeric($data->number) || empty($data->number))
            ) {
                $correct = true;
                $this->dbs->updateNumber($id, $data->number);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['journal_issues', 'articles']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Delete a journal issue
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            $this->dbs->delete($id);

            $this->cache->invalidateTags(['journal_issues']);

            $this->updateModified($old, null);

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
