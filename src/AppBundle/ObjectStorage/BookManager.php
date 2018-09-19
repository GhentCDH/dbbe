<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Book;

/**
 * ObjectManager for books
 * Servicename: book_manager
 */
class BookManager extends DocumentManager
{
    /**
     * Get books with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Book::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $books = [];
                $rawBooks = $this->dbs->getMiniInfoByIds($ids);

                foreach ($rawBooks as $rawBook) {
                    $book = new Book(
                        $rawBook['book_id'],
                        $rawBook['year'],
                        $rawBook['title'],
                        $rawBook['city'],
                        $rawBook['editor']
                    );

                    $books[$rawBook['book_id']] = $book;
                }

                $this->setPersonRoles($books);

                return $books;
            }
        );
    }

    /**
     * Get books with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->getMini($ids);
    }

    /**
     * Get a single book with all information
     * @param  int        $id
     * @return Book
     */
    public function getFull(int $id): Book
    {
        return $this->wrapSingleLevelCache(
            Book::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic book information
                $books = $this->getShort([$id]);
                if (count($books) == 0) {
                    throw new NotFoundHttpException('Book with id ' . $id .' not found.');
                }

                $this->setInverseBibliographies($books);

                $book = $books[$id];

                // Publisher, series, volume, total_volumes
                $rawBooks = $this->dbs->getFullInfoByIds([$id]);
                if (count($rawBooks) == 1) {
                    $book
                        ->setPublisher($rawBooks[0]['publisher'])
                        ->setSeries($rawBooks[0]['series'])
                        ->setVolume($rawBooks[0]['volume'])
                        ->setTotalVolumes($rawBooks[0]['total_volumes']);
                }

                return $book;
            }
        );
    }

    /**
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMini(string $sortFunction = null): array
    {
        return parent::getAllMini($sortFunction == null ? 'getDescription' : $sortFunction);
    }

    /**
     * Get all books that are dependent on a specific person
     * @param  int   $personId
     * @param  bool  $short    Whether to return a short or mini person (default: false => mini)
     * @return array
     */
    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short ? 'getShort' : 'getMini');
    }

    /**
     * Get all books that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getReferenceDependencies(array $referenceIds): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByReferenceIds($referenceIds), 'getMini');
    }

    /**
     * Add a new book
     * @param  stdClass $data
     * @return Book
     */
    public function add(stdClass $data): Book
    {
        if (!property_exists($data, 'title')
            || !is_string($data->title)
            || empty($data->title)
            || !property_exists($data, 'year')
            || !is_numeric($data->year)
            || empty($data->year)
            || !property_exists($data, 'city')
            || !is_string($data->city)
            || empty($data->city)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new book');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->title, $data->year, $data->city);

            unset($data->title);
            unset($data->year);
            unset($data->city);

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
     * Update new or existing book
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new book
     * @return Book
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Book
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Book with id ' . $id .' not found.');
            }

            $cacheReload = [
                'mini' => $isNew,
                'full' => $isNew,
            ];
            $roles = $this->container->get('role_manager')->getRolesByType('book');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $cacheReload['mini'] = true;
                    $this->updatePersonRoleWithRank($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'title')) {
                // Title is a required field
                if (!is_string($data->title) || empty($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            // Title is a required field
            if (property_exists($data, 'year')) {
                if (!is_numeric($data->year) || empty($data->year)) {
                    throw new BadRequestHttpException('Incorrect year data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateYear($id, $data->year);
            }
            // City is a required field
            if (property_exists($data, 'city')) {
                if (!is_string($data->city) || empty($data->city)) {
                    throw new BadRequestHttpException('Incorrect city data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateCity($id, $data->city);
            }
            if (property_exists($data, 'editor')) {
                if (!is_string($data->editor)) {
                    throw new BadRequestHttpException('Incorrect editor data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateEditor($id, $data->editor);
            }
            if (property_exists($data, 'publisher')) {
                if (!is_string($data->publisher)) {
                    throw new BadRequestHttpException('Incorrect publisher data.');
                }
                $cacheReload['full'] = true;
                $this->dbs->updatePublisher($id, $data->publisher);
            }
            if (property_exists($data, 'series')) {
                if (!is_string($data->series)) {
                    throw new BadRequestHttpException('Incorrect series data.');
                }
                $cacheReload['full'] = true;
                $this->dbs->updateSeries($id, $data->series);
            }
            if (property_exists($data, 'volume')) {
                if (!is_numeric($data->volume)) {
                    throw new BadRequestHttpException('Incorrect volume data.');
                }
                $cacheReload['full'] = true;
                $this->dbs->updateVolume($id, $data->volume);
            }
            if (property_exists($data, 'totalVolumes')) {
                if (!is_numeric($data->totalVolumes)) {
                    throw new BadRequestHttpException('Incorrect totalVolumes data.');
                }
                $cacheReload['full'] = true;
                $this->dbs->updateTotalVolumes($id, $data->totalVolumes);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            if (!$isNew) {
                $this->clearCache($id, $cacheReload);
            }
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            // (re-)index in elastic search
            $this->ess->add($new);

            // TODO: reset and re-index bookchapter dependencies

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

    /**
     * Delete a book
     * @param int $id
     */
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
