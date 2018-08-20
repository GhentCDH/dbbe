<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Psr\Cache\CacheItemPoolInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use AppBundle\Model\Book;
use AppBundle\Service\DatabaseService\DatabaseServiceInterface;
use AppBundle\Service\ElasticSearchService\ElasticSearchServiceInterface;

class BookManager extends DocumentManager
{
    public function __construct(
        DatabaseServiceInterface $databaseService,
        CacheItemPoolInterface $cacheItemPool,
        ContainerInterface $container,
        ElasticSearchServiceInterface $elasticSearchService = null,
        TokenStorageInterface $tokenStorage = null
    ) {
        parent::__construct($databaseService, $cacheItemPool, $container, $elasticSearchService, $tokenStorage);
        $this->en = 'book';
    }

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
                $rawBooks = $this->dbs->getBasicInfoByIds($ids);

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
        return $this->getShort($ids);
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

    public function getAllMini(): array
    {
        return $this->wrapArrayCache(
            'books',
            ['books'],
            function () {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, 'book_id');
                $books = $this->getMini($ids);

                // Sort by description
                usort($books, function ($a, $b) {
                    return strcmp($a->getDescription(), $b->getDescription());
                });

                return $books;
            }
        );
    }

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
            $bookId = $this->dbs->insert($data->title, $data->year, $data->city);

            unset($data->title);
            unset($data->year);
            unset($data->city);

            $newBook = $this->update($bookId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newBook;
    }

    public function update(int $id, stdClass $data, bool $new = false): Book
    {
        $this->dbs->beginTransaction();
        try {
            $book = $this->getFull($id);
            if ($book == null) {
                throw new NotFoundHttpException('Book with id ' . $id .' not found.');
            }

            // update book data
            $cacheReload = [
                'mini' => $new,
                'full' => $new,
            ];
            $roles = $this->container->get('role_manager')->getRolesByType('book');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $cacheReload['mini'] = true;
                    $this->updatePersonRoleWithRank($book, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'title')) {
                if (!is_string($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $cacheReload['mini'] = true;
                $this->updateTitle($book, $data->title);
            }
            if (property_exists($data, 'year')) {
                if (!is_numeric($data->year)) {
                    throw new BadRequestHttpException('Incorrect year data.');
                }
                $cacheReload['mini'] = true;
                $this->updateYear($book, $data->year);
            }
            if (property_exists($data, 'city')) {
                if (!is_string($data->city)) {
                    throw new BadRequestHttpException('Incorrect city data.');
                }
                $cacheReload['mini'] = true;
                $this->updateCity($book, $data->city);
            }
            if (property_exists($data, 'editor')) {
                if (!is_string($data->editor)) {
                    throw new BadRequestHttpException('Incorrect editor data.');
                }
                $cacheReload['mini'] = true;
                $this->updateEditor($book, $data->editor);
            }
            if (property_exists($data, 'publisher')) {
                if (!is_string($data->publisher)) {
                    throw new BadRequestHttpException('Incorrect publisher data.');
                }
                $cacheReload['full'] = true;
                $this->updatePublisher($book, $data->publisher);
            }
            if (property_exists($data, 'series')) {
                if (!is_string($data->series)) {
                    throw new BadRequestHttpException('Incorrect series data.');
                }
                $cacheReload['full'] = true;
                $this->updateSeries($book, $data->series);
            }
            if (property_exists($data, 'volume')) {
                if (!is_numeric($data->volume)) {
                    throw new BadRequestHttpException('Incorrect volume data.');
                }
                $cacheReload['full'] = true;
                $this->updateVolume($book, $data->volume);
            }
            if (property_exists($data, 'totalVolumes')) {
                if (!is_numeric($data->totalVolumes)) {
                    throw new BadRequestHttpException('Incorrect totalVolumes data.');
                }
                $cacheReload['full'] = true;
                $this->updateTotalVolumes($book, $data->totalVolumes);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new book data
            $this->clearCache($id, $cacheReload);
            $newBook = $this->getFull($id);

            $this->updateModified($new ? null : $book, $newBook);

            // Reset cache and elasticsearch
            // TODO
            // TODO: check dependencies to clear cache and update elasticsearch

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($newBook)) {
                $this->reset([$id]);
            }
            throw $e;
        }

        return $newBook;
    }

    private function updateTitle(Book $book, string $title): void
    {
        // Title is a required field
        if (empty($title)) {
            throw new BadRequestHttpException('Incorrect title data.');
        }

        $this->dbs->updateTitle($book->getId(), $title);
    }

    private function updateYear(Book $book, int $year): void
    {
        // Year is a required field
        if (empty($year)) {
            throw new BadRequestHttpException('Incorrect year data.');
        }

        $this->dbs->updateYear($book->getId(), $year);
    }
    private function updateCity(Book $book, string $city): void
    {
        // City is a required field
        if (empty($city)) {
            throw new BadRequestHttpException('Incorrect city data.');
        }

        $this->dbs->updateCity($book->getId(), $city);
    }
    private function updateEditor(Book $book, string $editor): void
    {
        $this->dbs->updateEditor($book->getId(), $editor);
    }
    private function updatePublisher(Book $book, string $publisher): void
    {
        $this->dbs->updatePublisher($book->getId(), $publisher);
    }
    private function updateSeries(Book $book, string $series): void
    {
        $this->dbs->updateSeries($book->getId(), $series);
    }
    private function updateVolume(Book $book, string $volume): void
    {
        $this->dbs->updateVolume($book->getId(), $volume);
    }
    private function updateTotalVolumes(Book $book, string $totalVolumes): void
    {
        $this->dbs->updateTotalVolumes($book->getId(), $totalVolumes);
    }

    // TODO: delete
}
