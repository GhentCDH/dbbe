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
        return $this->wrapCache(
            Journal::CACHENAME,
            $ids,
            function ($ids) {
                $journals = [];
                $rawJournals = $this->dbs->getJournalsByIds($ids);

                foreach ($rawJournals as $rawJournal) {
                    $journals[$rawJournal['journal_id']] = new Journal(
                        $rawJournal['journal_id'],
                        $rawJournal['title'],
                        $rawJournal['year'],
                        $rawJournal['volume'],
                        $rawJournal['number']
                    );
                }

                return $journals;
            }
        );
    }

    /**
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAll(string $sortFunction = null): array
    {
        return parent::getAll($sortFunction == null ? 'getTitle' : $sortFunction);
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
            # insert with mandatory fields
            $journalId = $this->dbs->insert($data->title, $data->year, $data->volume, $data->number);

            $newJournal = $this->get([$journalId])[$journalId];

            $this->updateModified(null, $newOffice);

            // update cache
            $this->cache->invalidateTags(['offices']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newJournal;
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
            $this->deleteCache(Journal::CACHENAME, $id);
            $newJournal = $this->get([$id])[$id];

            $this->updateModified($journals[$id], $newJournal);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newJournal;
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

            // clear cache
            $this->cache->invalidateTags(['journals']);
            $this->deleteCache(Journal::CACHENAME, $id);

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
