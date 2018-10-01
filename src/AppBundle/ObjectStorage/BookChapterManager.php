<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\BookChapter;

/**
 * ObjectManager for book chapters
 * Servicename: book_chapter_manager
 */
class BookChapterManager extends DocumentManager
{
    /**
     * Get book chapters with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            BookChapter::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $bookChapters = [];
                $rawBookChapters = $this->dbs->getMiniInfoByIds($ids);

                $bookIds = self::getUniqueIds($rawBookChapters, 'book_id');
                $books = $this->container->get('book_manager')->getMini($bookIds);

                foreach ($rawBookChapters as $rawBookChapter) {
                    $bookChapter = (new BookChapter(
                        $rawBookChapter['book_chapter_id'],
                        $rawBookChapter['book_chapter_title'],
                        $books[$rawBookChapter['book_id']]
                    ))
                        ->setStartPage($rawBookChapter['book_chapter_page_start'])
                        ->setEndPage($rawBookChapter['book_chapter_page_end'])
                        ->setRawPages($rawBookChapter['book_chapter_raw_pages']);

                    $bookChapters[$rawBookChapter['book_chapter_id']] = $bookChapter;
                }

                $this->setPersonRoles($bookChapters);

                return $bookChapters;
            }
        );
    }

    /**
     * Get book chapters with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->getMini($ids);
    }

    /**
     * Get a single book chapter with all information
     * @param  int        $id
     * @return BookChapter
     */
    public function getFull(int $id): BookChapter
    {
        return $this->wrapSingleLevelCache(
            BookChapter::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic information
                $bookChapters = $this->getShort([$id]);

                if (count($bookChapters) == 0) {
                    throw new NotFoundHttpException('Book chapter with id ' . $id .' not found.');
                }

                $this->setIdentifications($bookChapters);

                $this->setInverseBibliographies($bookChapters);

                return $bookChapters[$id];
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
     * Get all book chapters that are dependent on a specific book
     * @param  int   $bookId
     * @return array
     */
    public function getBookDependencies(int $bookId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookId($bookId), 'getMini');
    }

    /**
     * Get all book chapters that are dependent on a specific person
     * @param  int   $personId
     * @param  bool  $short    Whether to return a short or mini person (default: false => mini)
     * @return array
     */
    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short ? 'getShort' : 'getMini');
    }

    /**
     * Get all book chapters that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getReferenceDependencies(array $referenceIds): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByReferenceIds($referenceIds), 'getMini');
    }

    /**
     * Add a new book chapter
     * @param  stdClass $data
     * @return BookChapter
     */
    public function add(stdClass $data): BookChapter
    {
        if (!property_exists($data, 'title')
            || !is_string($data->title)
            || empty($data->title)
            || !property_exists($data, 'book')
            || !is_object($data->book)
            || !property_exists($data->book, 'id')
            || !is_numeric($data->book->id)
            || empty($data->book->id)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new book chapter');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->title, $data->book->id);

            unset($data->title);
            unset($data->book);

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
     * Update new or existing book chapter
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new book chapter
     * @return BookChapter
     */
    public function update(int $id, stdClass $data, bool $isNew = false): BookChapter
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Book chapter with id ' . $id .' not found.');
            }

            $cacheReload = [
                'mini' => $isNew,
            ];
            $roles = $this->container->get('role_manager')->getRolesByType('bookChapter');
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
            if (property_exists($data, 'book')) {
                // Book is a required field
                if (!is_object($data->book)
                    || !property_exists($data->book, 'id')
                    || !is_numeric($data->book->id)
                    || empty($data->book->id)
                ) {
                    throw new BadRequestHttpException('Incorrect book data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateBook($id, $data->book->id);
            }
            $this->updateIdentificationwrapper($old, $data, $cacheReload, 'full', 'bookChapter');

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $this->clearCache($id, $cacheReload);
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            // (re-)index in elastic search
            $this->ess->add($new);

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
     * Delete a book chapter
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
