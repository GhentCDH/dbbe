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
    public function getMini(array $ids): array
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

    public function getShort(array $ids): array
    {
        $journals = $this->getMini($ids);
        $this->setManagements($journals);

        return $journals;
    }

    public function getFull(int $id): Journal
    {
        $journals = $this->getShort([$id]);
        if (count($journals) == 0) {
            throw new NotFoundHttpException('Journal with id ' . $id .' not found.');
        }
        return $journals[$id];
    }

    /**
     * Get all journals
     * @return array
     */
    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'journal_id');
        return $this->getMini($ids);
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
     * Get a list with all related issues and articles
     * @param int $id
     * @return array Structure: [journalIssueId => [issue, [article, article]], ...]] (ordered by year, volume, number)
     */
    public function getIssuesArticles(int $id) {
        $raws = $this->dbs->getIssuesArticles($id);

        $articleIds = self::getUniqueIds($raws, 'article_id');
        $articles = $this->container->get('article_manager')->getMini($articleIds);
        $journalIssueIds = self::getUniqueIds($raws, 'journal_issue_id');
        $journalIssues = $this->container->get('journal_issue_manager')->getMini($journalIssueIds);

        $issuesArticles = [];

        foreach ($raws as $raw) {
            if (!isset($issuesArticles[$raw['journal_issue_id']])) {
                $issuesArticles[$raw['journal_issue_id']] = [$journalIssues[$raw['journal_issue_id']], []];
            }
            $issuesArticles[$raw['journal_issue_id']][1][] = $articles[$raw['article_id']];
        }
        return $issuesArticles;
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

            $new = $this->getFull($id);

            $this->updateModified(null, $new);

            $this->cache->invalidateTags(['journals']);

            // (re-)index in elastic search
            $this->ess->add($new);

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
        // Throws NotFoundException if not found
        $old = $this->getFull($id);

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

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['journals', 'journal_issues', 'articles']);

            // (re-)index in elastic search
            $this->ess->add($new);

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
        if ($primaryId == $secondaryId) {
            throw new BadRequestHttpException(
                'Journals with id ' . $primaryId .' and id ' . $secondaryId . ' are identical and cannot be merged.'
            );
        }
        // Throws NotFoundException if not found
        $primary = $this->getFull($primaryId);
        $this->getFull($secondaryId);

        $journalIssues = $this->container->get('journal_issue_manager')->getJournalDependencies($secondaryId, 'get');

        $this->dbs->beginTransaction();
        try {
            if (!empty($journalIssues)) {
                foreach ($journalIssues as $journalIssue) {
                    $this->container->get('journal_issue_manager')->update(
                        $journalIssue->getId(),
                        json_decode(
                            json_encode(
                                [
                                    'journal' => [
                                        'id' => $primaryId,
                                    ],
                                ]
                            )
                        )
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
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            $this->cache->invalidateTags(['journals']);

            // (re-)index in elastic search
            $this->ess->delete($id);

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
