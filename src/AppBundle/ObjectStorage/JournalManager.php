<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Journal;
use AppBundle\Model\Url;
use AppBundle\Utils\ArrayToJson;
use AppBundle\Utils\GreekNormalizer;

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

        $this->setUrls($journals);

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
    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        $journals = parent::getAllMiniShortJson($sortFunction == null ? 'getTitle' : $sortFunction);

        // Resort by name (remove greek accents)
        usort($journals, function ($a, $b) {
            return strcmp(GreekNormalizer::normalize($a['name']), GreekNormalizer::normalize($b['name']));
        });

        return $journals;
    }

    /**
     * Get all journals with all information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllJson(string $sortFunction = null): array
    {
        $rawJournals = $this->dbs->getAll();
        $journals = [];

        foreach ($rawJournals as $rawJournal) {
            $journal = new Journal($rawJournal['journal_id'], $rawJournal['title']);
            $urlIds = json_decode($rawJournal['url_ids']);
            $urlUrls = json_decode($rawJournal['url_urls']);
            $urlTitles = json_decode($rawJournal['url_titles']);
            if (!(count($urlIds) == 1 && $urlIds[0] == null)) {
                for ($i = 0; $i < count($urlIds); $i++) {
                    $journal->addUrl(new Url($urlIds[$i], $urlUrls[$i], $urlTitles[$i]));
                }
            }
            $journals[] = $journal;
        }

        $sortFunction = $sortFunction ?? 'getTitle';

        usort($journals, function ($a, $b) use ($sortFunction) {
            return $a->{$sortFunction}() <=> $b->{$sortFunction}();
        });

        return ArrayToJson::arrayToJson($journals);
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
            !property_exists($data, 'name')
            || !is_string($data->name)
            || empty($data->name)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new journal');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->name);

            unset($data->name);

            $new = $this->update($id, $data, true);

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
     * @param  bool     $isNew Indicate whether this is a new book cluster
     * @return Journal
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Journal
    {
        // Throws NotFoundException if not found
        $old = $this->getFull($id);

        $this->dbs->beginTransaction();
        try {
            $changes = [
                'mini' => $isNew,
                'full' => $isNew,
            ];
            if (property_exists($data, 'name')
                && is_string($data->name)
                && !empty($data->name)
            ) {
                $correct = true;
                $this->dbs->updateTitle($id, $data->name);
            }
            $this->updateUrlswrapper($old, $data, $changes, 'full');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
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

        $journalIssues = $this->container->get('journal_issue_manager')->getJournalDependencies($secondaryId);

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
