<?php

namespace App\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Exceptions\DependencyException;
use App\Model\JournalIssue;
use App\Utils\GreekNormalizer;

/**
 * ObjectManager for journal issues
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
        $journals = $this->container->get(JournalManager::class)->getMini($journalIds);

        foreach ($rawJournalIssues as $rawJournalIssue) {
            $journalIssues[$rawJournalIssue['journal_issue_id']] = new JournalIssue(
                $rawJournalIssue['journal_issue_id'],
                $journals[$rawJournalIssue['journal_id']],
                $rawJournalIssue['year'],
                $rawJournalIssue['forthcoming'],
                $rawJournalIssue['series'],
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
        $journalIssues = $this->getAllCombinedJson('short', $sortFunction);

        // Resort by name (remove greek accents)
        usort($journalIssues, function ($a, $b) {
            return strcmp(GreekNormalizer::normalize($a['name']), GreekNormalizer::normalize($b['name']));
        });

        return $journalIssues;
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
            # optional
            || (
                property_exists($data, 'year')
                && !empty($data->year)
                && !is_string($data->year)
            )
            || (
                !property_exists($data, 'forthcoming')
                || !is_bool($data->forthcoming)
            )
            || (
                $data->forthcoming == FALSE
                && (
                    !property_exists($data, 'year')
                    || empty($data->year)
                )
            )
            || (
                property_exists($data, 'volume')
                && !empty($data->volume)
                && !is_string($data->volume)
            )
            || (
                property_exists($data, 'number')
                && !empty($data->number)
                && !is_string($data->number)
            )
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new journal issue');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert(
                $data->journal->id,
                property_exists($data, 'year') ? $data->year : null,
                $data->forthcoming,
                property_exists($data, 'series') ? $data->series : null,
                property_exists($data, 'volume') ? $data->volume : null,
                property_exists($data, 'number') ? $data->number : null
            );

            $new = $this->getFull($id);

            $this->updateModified(null, $new);

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
            // Year is required if not forthcoming
            if (property_exists($data, 'year')) {
                if (!empty($data->year) && !is_string($data->year)) {
                    throw new BadRequestHttpException('Incorrect year data.');
                }
                if (empty($data->year)
                    && (
                        (
                            !property_exists($data, 'forthcoming')
                            && $old->getForthcoming() == FALSE
                        )
                        || (
                            property_exists($data, 'forthcoming')
                            && $data->forthcoming == FALSE
                        )
                    )
                ) {
                    throw new BadRequestHttpException('Year or forthcoming is required.');
                }
                $changes['mini'] = true;
                $this->dbs->updateYear($id, $data->year);
            }
            if (property_exists($data, 'forthcoming')) {
                if (!is_bool($data->forthcoming)) {
                    throw new BadRequestHttpException('Incorrect forthcoming data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateForthcoming($id, $data->forthcoming);
            }
            if (property_exists($data, 'series')
                && (is_string($data->series) || empty($data->series))
            ) {
                $correct = true;
                $this->dbs->updateSeries($id, $data->series);
            }
            if (property_exists($data, 'volume')
                && (is_string($data->volume) || empty($data->volume))
            ) {
                $correct = true;
                $this->dbs->updateVolume($id, $data->volume);
            }
            if (property_exists($data, 'number')
                && (is_string($data->number) || empty($data->number))
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
