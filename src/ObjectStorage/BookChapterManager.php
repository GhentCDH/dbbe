<?php

namespace App\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Model\BookChapter;

/**
 * ObjectManager for book chapters
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
            $books = $this->container->get(BookManager::class)->getMini($bookIds);

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

        $this->setCreatedAndModifiedDates($bookChapters);

        $this->setInverseIdentifications($bookChapters);

        $this->setInverseBibliographies($bookChapters);

        $this->setUrls($bookChapters);

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
            $roles = $this->container->get(RoleManager::class)->getByType('bookChapter');
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
            if (property_exists($data, 'startPage')) {
                if (!is_numeric($data->startPage)) {
                    throw new BadRequestHttpException('Incorrect start page data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateStartPage($id, $data->startPage);
            }
            if (property_exists($data, 'endPage')) {
                // StartPage is required if endPage is given
                if (!is_numeric($data->endPage) || (!empty($data->endPage) && empty($data->startPage) && empty($old->getStartPage()))) {
                    throw new BadRequestHttpException('Incorrect start page data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateEndPage($id, $data->endPage);
            }
            $this->updateUrlswrapper($old, $data, $changes, 'full');
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePublicComment($id, $data->publicComment);
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

            // (re-)index in elastic search
            $this->ess->add($new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if ($isNew) {
                $this->deleteElasticByIdIfExists($id);
            } elseif (isset($new) && isset($old)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }
}
