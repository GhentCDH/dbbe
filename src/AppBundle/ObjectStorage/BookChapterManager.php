<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $bookChapters = [];
        if (!empty($ids)) {
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
        }

        return $bookChapters;
    }

    /**
     * Get book chapters with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $bookChapters = $this->getMini($ids);

        $this->setIdentifications($bookChapters);

        $this->setComments($bookChapters);

        $this->setManagements($bookChapters);

        return $bookChapters;
    }

    /**
     * Get a single book chapter with all information
     * @param  int        $id
     * @return BookChapter
     */
    public function getFull(int $id): BookChapter
    {
        // Get basic information
        $bookChapters = $this->getShort([$id]);

        if (count($bookChapters) == 0) {
            throw new NotFoundHttpException('Book chapter with id ' . $id .' not found.');
        }

        $this->setModifieds($bookChapters);

        $this->setInverseBibliographies($bookChapters);

        return $bookChapters[$id];
    }

    /**
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return parent::getAllMiniShortJson($sortFunction == null ? 'getDescription' : $sortFunction);
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

            $changes = [
                'mini' => $isNew,
            ];
            $roles = $this->container->get('role_manager')->getByType('bookChapter');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['mini'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'title')) {
                // Title is a required field
                if (!is_string($data->title) || empty($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $changes['mini'] = true;
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
                $changes['mini'] = true;
                $this->dbs->updateBook($id, $data->book->id);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'bookChapter');
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // (re-)index in elastic search
            $this->ess->add($new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if (!$isNew && isset($new)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }
}
