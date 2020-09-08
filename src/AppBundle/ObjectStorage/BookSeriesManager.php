<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\BookCluster;
use AppBundle\Model\Url;
use AppBundle\Utils\ArrayToJson;
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
        $rawBooks = $this->dbs->getBooks([$id]);
        $bookIds = self::getUniqueIds($rawBooks, 'book_id');
        $books = $this->container->get('book_manager')->getMini($bookIds);
        foreach ($rawBooks as $rawBook) {
            $bookSeriess[$rawBook['book_series_id']]->addBook($books[$rawBook['book_id']]);
        }

        $this->setCreatedAndModifiedDates($bookSeriess);

        $this->setUrls($bookSeriess);

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
        $rawBookSeriess = $this->dbs->getAll();
        $bookSeriess = [];

        foreach ($rawBookSeriess as $rawBookSeries) {
            $bookSeries = new BookCluster($rawBookSeries['book_cluster_id'], $rawBookSeries['title']);
            $urlIds = json_decode($rawBookSeries['url_ids']);
            $urlUrls = json_decode($rawBookSeries['url_urls']);
            $urlTitles = json_decode($rawBookSeries['url_titles']);
            if (!(count($urlIds) == 1 && $urlIds[0] == null)) {
                for ($i = 0; $i < count($urlIds); $i++) {
                    $bookSeries->addUrl(new Url($urlIds[$i], $urlUrls[$i], $urlTitles[$i]));
                }
            }
            $bookSeriess[] = $bookSeries;
        }

        usort($bookSeriess, function ($a, $b) use ($sortFunction) {
            return $a->{$sortFunction}() <=> $b->{$sortFunction}();
        });

        return ArrayToJson::arrayToJson($bookSeriess);
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
     * Update an existing book series
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new book cluster
     * @return BookSeries
     */
    public function update(int $id, stdClass $data, bool $isNew = false): BookSeries
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
                $changes['mini'] = true;
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
