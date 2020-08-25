<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\BookSeries;
use AppBundle\Utils\VolumeSortKey;

/**
 * ObjectManager for book seriess
 * Servicename: book_series_manager
 */
class BookSeriesManager extends DocumentManager
{
    /**
     * Get book seriess with all information
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $bookSeriess = [];
        $rawBookSeriess = $this->dbs->getBookSeriessByIds($ids);

        foreach ($rawBookSeriess as $rawBookSeries) {
            $bookSeriess[$rawBookSeries['book_series_id']] = new BookSeries(
                $rawBookSeries['book_series_id'],
                $rawBookSeries['title']
            );
        }

        return $bookSeriess;
    }

    public function getShort(array $ids): array
    {
        $bookSeriess = $this->getMini($ids);
        $this->setManagements($bookSeriess);

        return $bookSeriess;
    }

    public function getFull(int $id): BookSeries
    {
        $bookSeriess = $this->getShort([$id]);
        if (count($bookSeriess) == 0) {
            throw new NotFoundHttpException('Book series with id ' . $id .' not found.');
        }
        return $bookSeriess[$id];
    }

    /**
     * Get all book seriess
     * @return array
     */
    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'book_series_id');
        return $this->getMini($ids);
    }

    /**
     * Get all book seriess with minimal information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return parent::getAllMiniShortJson($sortFunction == null ? 'getTitle' : $sortFunction);
    }

    /**
     * Get all book seriess with all information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllJson(string $sortFunction = null): array
    {
        return parent::getAllJson($sortFunction == null ? 'getTitle' : $sortFunction);
    }

    /**
     * Get a list with all related books
     * @param int $id
     * @return array (ordered by volume)
     */
    public function getBooks(int $id) {
        $raws = $this->dbs->getBooks($id);

        $bookIds = self::getUniqueIds($raws, 'book_id');
        $books = $this->container->get('book_manager')->getMini($bookIds);

        usort(
            $books,
            function ($a, $b) {
                return strcmp(VolumeSortKey::sortKey($a->getVolume()), VolumeSortKey::sortKey($b->getVolume()));
            }
        );

        return $books;
    }

    /**
     * Add a new book series
     * @param  stdClass $data
     * @return BookSeries
     */
    public function add(stdClass $data): BookSeries
    {
        if (# mandatory
            !property_exists($data, 'name')
            || !is_string($data->name)
            || empty($data->name)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new book series');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->name);

            $new = $this->getFull($id);

            $this->updateModified(null, $new);

            $this->cache->invalidateTags(['book_seriess']);

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
     * Update an existing book series
     * @param  int      $id
     * @param  stdClass $data
     * @return BookSeries
     */
    public function update(int $id, stdClass $data): BookSeries
    {
        // Throws NotFoundException if not found
        $old = $this->getFull($id);

        $this->dbs->beginTransaction();
        try {
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
                && !empty($data->name)
            ) {
                $correct = true;
                $this->dbs->updateTitle($id, $data->name);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['book_seriess', 'books']);

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
     * Merge two book seriess
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return BookSeries
     */
    public function merge(int $primaryId, int $secondaryId): BookSeries
    {
        if ($primaryId == $secondaryId) {
            throw new BadRequestHttpException(
                'Book series with id ' . $primaryId .' and id ' . $secondaryId . ' are identical and cannot be merged.'
            );
        }
        // Throws NotFoundException if not found
        $primary = $this->getFull($primaryId);
        $this->getFull($secondaryId);

        $books = $this->container->get('book_manager')->getBookSeriesDependencies($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($books)) {
                foreach ($books as $book) {
                    $this->container->get('book_manager')->update(
                        $book->getId(),
                        json_decode(
                            json_encode(
                                [
                                    'bookSeries' => [
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
     * Delete a book series
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

            $this->cache->invalidateTags(['book_seriess']);

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
