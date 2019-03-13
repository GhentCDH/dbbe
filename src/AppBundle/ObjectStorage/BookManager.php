<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $books = [];
        if (!empty($ids)) {
            $rawBooks = $this->dbs->getMiniInfoByIds($ids);

            foreach ($rawBooks as $rawBook) {
                $book = new Book(
                    $rawBook['book_id'],
                    $rawBook['year'],
                    $rawBook['title'],
                    $rawBook['city'],
                    $rawBook['editor'],
                    $rawBook['volume']
                );

                $books[$rawBook['book_id']] = $book;
            }

            $this->setPersonRoles($books);
        }

        return $books;
    }

    /**
     * Get books with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $books = $this->getMini($ids);

        $this->setIdentifications($books);

        $this->setComments($books);

        $this->setManagements($books);

        return $books;
    }

    /**
     * Get a single book with all information
     * @param  int        $id
     * @return Book
     */
    public function getFull(int $id): Book
    {
        // Get basic book information
        $books = $this->getShort([$id]);

        if (count($books) == 0) {
            throw new NotFoundHttpException('Book with id ' . $id .' not found.');
        }

        $this->setModifieds($books);

        $this->setInverseIdentifications($books);

        $this->setInverseBibliographies($books);

        $book = $books[$id];

        // Publisher, series, volume, total_volumes
        $rawBooks = $this->dbs->getFullInfoByIds([$id]);
        if (count($rawBooks) == 1) {
            $book
                ->setPublisher($rawBooks[0]['publisher'])
                ->setSeries($rawBooks[0]['series'])
                ->setTotalVolumes($rawBooks[0]['total_volumes']);
        }

        // Chapters
        $rawChapters = $this->dbs->getchapters([$id]);
        $bookChapterIds = self::getUniqueIds($rawChapters, 'book_chapter_id');
        $bookChapters = $this->container->get('book_chapter_manager')->getMini($bookChapterIds);
        foreach ($rawChapters as $rawChapter) {
            $book->addChapter($bookChapters[$rawChapter['book_chapter_id']]);
        }
        $book->sortChapters();

        return $book;
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

            $changes = [
                'mini' => $isNew,
                'full' => $isNew,
            ];
            $roles = $this->container->get('role_manager')->getByType('book');
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
            // Title is a required field
            if (property_exists($data, 'year')) {
                if (!is_numeric($data->year) || empty($data->year)) {
                    throw new BadRequestHttpException('Incorrect year data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateYear($id, $data->year);
            }
            // City is a required field
            if (property_exists($data, 'city')) {
                if (!is_string($data->city) || empty($data->city)) {
                    throw new BadRequestHttpException('Incorrect city data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateCity($id, $data->city);
            }
            if (property_exists($data, 'publisher')) {
                if (!is_string($data->publisher)) {
                    throw new BadRequestHttpException('Incorrect publisher data.');
                }
                $changes['full'] = true;
                $this->dbs->updatePublisher($id, $data->publisher);
            }
            if (property_exists($data, 'series')) {
                if (!is_string($data->series)) {
                    throw new BadRequestHttpException('Incorrect series data.');
                }
                $changes['full'] = true;
                $this->dbs->updateSeries($id, $data->series);
            }
            if (property_exists($data, 'volume')) {
                if (!is_numeric($data->volume)) {
                    throw new BadRequestHttpException('Incorrect volume data.');
                }
                $changes['full'] = true;
                $this->dbs->updateVolume($id, $data->volume);
            }
            if (property_exists($data, 'totalVolumes')) {
                if (!is_numeric($data->totalVolumes)) {
                    throw new BadRequestHttpException('Incorrect totalVolumes data.');
                }
                $changes['full'] = true;
                $this->dbs->updateTotalVolumes($id, $data->totalVolumes);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'book');
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags(['books', 'book_chapters']);

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
