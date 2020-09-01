<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\BookCluster;
use AppBundle\Utils\VolumeSortKey;

/**
 * ObjectManager for book clusters
 * Servicename: book_cluster_manager
 */
class BookClusterManager extends DocumentManager
{
    /**
     * Get book clusters with all information
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $bookClusters = [];
        $rawBookClusters = $this->dbs->getBookClustersByIds($ids);

        foreach ($rawBookClusters as $rawBookCluster) {
            $bookClusters[$rawBookCluster['book_cluster_id']] = new BookCluster(
                $rawBookCluster['book_cluster_id'],
                $rawBookCluster['title']
            );
        }

        return $bookClusters;
    }

    public function getShort(array $ids): array
    {
        $bookClusters = $this->getMini($ids);

        $rawBooks = $this->dbs->getBooks($ids);
        $bookIds = self::getUniqueIds($rawBooks, 'book_id');
        $books = $this->container->get('book_manager')->getMini($bookIds);
        foreach ($rawBooks as $rawBook) {
            $bookClusters[$rawBook['book_cluster_id']]->addBook($books[$rawBook['book_id']]);
        }

        $this->setManagements($bookClusters);

        return $bookClusters;
    }

    public function getFull(int $id): BookCluster
    {
        $bookClusters = $this->getShort([$id]);
        if (count($bookClusters) == 0) {
            throw new NotFoundHttpException('Book cluster with id ' . $id .' not found.');
        }
        return $bookClusters[$id];
    }

    /**
     * Get all book clusters
     * @return array
     */
    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'book_cluster_id');
        return $this->getMini($ids);
    }

    /**
     * Get all book clusters with minimal information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return parent::getAllMiniShortJson($sortFunction == null ? 'getTitle' : $sortFunction);
    }

    /**
     * Get all book clusters with all information
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllJson(string $sortFunction = null): array
    {
        return parent::getAllJson($sortFunction == null ? 'getTitle' : $sortFunction);
    }

    /**
     * Add a new book cluster
     * @param  stdClass $data
     * @return BookCluster
     */
    public function add(stdClass $data): BookCluster
    {
        if (# mandatory
            !property_exists($data, 'name')
            || !is_string($data->name)
            || empty($data->name)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new book cluster');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->name);

            $new = $this->getFull($id);

            $this->updateModified(null, $new);

            $this->cache->invalidateTags(['book_clusters']);

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
     * Update an existing book cluster
     * @param  int      $id
     * @param  stdClass $data
     * @return BookCluster
     */
    public function update(int $id, stdClass $data): BookCluster
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

            $this->cache->invalidateTags(['book_clusters', 'books']);

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
     * Merge two book clusters
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return BookCluster
     */
    public function merge(int $primaryId, int $secondaryId): BookCluster
    {
        if ($primaryId == $secondaryId) {
            throw new BadRequestHttpException(
                'Book clusters with id ' . $primaryId .' and id ' . $secondaryId . ' are identical and cannot be merged.'
            );
        }
        // Throws NotFoundException if not found
        $primary = $this->getFull($primaryId);
        $this->getFull($secondaryId);

        $books = $this->container->get('book_manager')->getBookClusterDependencies($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($books)) {
                foreach ($books as $book) {
                    $this->container->get('book_manager')->update(
                        $book->getId(),
                        json_decode(
                            json_encode(
                                [
                                    'bookCluster' => [
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
     * Delete a book cluster
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

            $this->cache->invalidateTags(['book_clusters']);

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
