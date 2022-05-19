<?php

namespace App\ObjectStorage;

use Psr\Cache\InvalidArgumentException;
use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Model\ArticleBibliography;
use App\Model\BlogPostBibliography;
use App\Model\Book;
use App\Model\BookBibliography;
use App\Model\BookChapterBibliography;
use App\Model\OnlineSourceBibliography;
use App\Model\PhdBibliography;
use App\Utils\ArrayToJson;

/**
 * ObjectManager for books
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

            $bookClusterIds = self::getUniqueIds($rawBooks, 'book_cluster_id');
            $bookClusters = $this->container->get(BookClusterManager::class)->getMini($bookClusterIds);

            foreach ($rawBooks as $rawBook) {
                $book = new Book(
                    $rawBook['book_id'],
                    $rawBook['year'],
                    $rawBook['forthcoming'],
                    $rawBook['city'],
                    $rawBook['title'],
                    $rawBook['book_cluster_id'] != null ? $bookClusters[$rawBook['book_cluster_id']] : null,
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

        $this->setCreatedAndModifiedDates($books);

        $this->setInverseIdentifications($books);

        $this->setInverseBibliographies($books);

        $this->setUrls($books);

        $book = $books[$id];

        // Publisher, series, volume, total_volumes
        $rawBooks = $this->dbs->getFullInfoByIds([$id]);

        if (count($rawBooks) == 1) {
            $book
                ->setPublisher($rawBooks[0]['publisher'])
                ->setSeriesVolume($rawBooks[0]['series_volume'])
                ->setTotalVolumes($rawBooks[0]['total_volumes']);

            $BookSeriesIds = self::getUniqueIds($rawBooks, 'book_series_id');
            $BookSeriess = $this->container->get(BookSeriesManager::class)->getMini($BookSeriesIds);
            if ($rawBooks[0]['book_series_id'] != null) {
                $book->setSeries($BookSeriess[$rawBooks[0]['book_series_id']]);
            }
        }

        // Chapters
        $rawChapters = $this->dbs->getChapters([$id]);
        $bookChapterIds = self::getUniqueIds($rawChapters, 'book_chapter_id');
        $bookChapters = $this->container->get(BookChapterManager::class)->getMini($bookChapterIds);
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
     * Get all books that are dependent on a book cluster
     * @param  int $bookClusterId
     * @return array
     */
    public function getBookClusterDependencies(int $bookClusterId, string $method = 'getMini'): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookClusterId($bookClusterId), $method);
    }

    /**
     * Get all books that are dependent on a book series
     * @param  int $bookSeriesId
     * @return array
     */
    public function getBookSeriesDependencies(int $bookSeriesId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByBookSeriesId($bookSeriesId), 'getMini');
    }

    /**
     * Add a new book
     * @param stdClass $data
     * @return Book
     * @throws Exception|InvalidArgumentException
     */
    public function add(stdClass $data): Book
    {
        if ((
                (!property_exists($data, 'title') || empty($data->title))
                && (!property_exists($data, 'bookCluster') || empty($data->bookCluster))
            )
            || (
                property_exists($data, 'title')
                && (
                    !is_string($data->title)
                    || empty($data->title)
                )
            )
            || (
                property_exists($data, 'bookCluster')
                && (
                    !is_object($data->bookCluster)
                    || !property_exists($data->bookCluster, 'id')
                    || !is_numeric($data->bookCluster->id)
                    || empty($data->bookCluster->id)
                )
            )
            || (
                property_exists($data, 'year')
                && !empty($data->year)
                && !is_numeric($data->year)
            )
            || (
                !property_exists($data, 'forthcoming')
                || !is_bool($data->forthcoming)
            )
            || (
                $data->forthcoming == FALSE
                && (
                    !property_exists($data, 'year')
                    || empty($data->year)
                )
            )
            || !property_exists($data, 'city')
            || !is_string($data->city)
            || empty($data->city)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new book');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert(
                property_exists($data, 'bookCluster') ? $data->bookCluster->id: null,
                property_exists($data, 'title') ? $data->title : null,
                property_exists($data, 'year') ? $data->year : null,
                $data->forthcoming,
                $data->city
            );

            unset($data->bookCluster);
            unset($data->title);
            unset($data->year);
            unset($data->forthcoming);
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
     * @param int $id
     * @param stdClass $data
     * @param bool $isNew Indicate whether this is a new book
     * @return Book
     * @throws InvalidArgumentException
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
            $roles = $this->container->get(RoleManager::class)->getByType('book');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['mini'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'bookCluster')) {
                if (!empty($data->bookCluster)
                    && (
                        !is_object($data->bookCluster)
                        || !property_exists($data->bookCluster, 'id')
                        || !is_numeric($data->bookCluster->id)
                        || empty($data->bookCluster->id)
                    )
                ) {
                    throw new BadRequestHttpException('Incorrect book cluster data.');
                }
                if (empty($data->bookCluster)
                    && (
                        (
                            !property_exists($data, 'title')
                            && $old->getTitle() == null
                        )
                        || (
                            property_exists($data, 'title')
                            && empty($data->title)
                        )
                    )
                ) {
                    throw new BadRequestHttpException('Book cluster or title is required.');
                }
                $changes['mini'] = true;
                $this->dbs->updateBookCluster($id, empty($data->bookCluster) ? null : $data->bookCluster->id);
            }
            if (property_exists($data, 'title')) {
                if (!empty($data->title)
                    && !is_string($data->title)
                ) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                if (empty($data->title)
                    && (
                        (
                            !property_exists($data, 'bookCluster')
                            && $old->getBookCluster == null
                        )
                        || (
                            property_exists($data, 'bookCluster')
                            && empty($data->bookCluster)
                        )
                    )
                ) {
                    throw new BadRequestHttpException('Book cluster or title is required.');
                }
                $changes['mini'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            // Year is required if not forthcoming
            if (property_exists($data, 'year')) {
                if (!empty($data->year) && !is_numeric($data->year)) {
                    throw new BadRequestHttpException('Incorrect year data.');
                }
                if (empty($data->year)
                    && (
                        (
                            !property_exists($data, 'forthcoming')
                            && $old->getForthcoming() == FALSE
                        )
                        || (
                            property_exists($data, 'forthcoming')
                            && $data->forthcoming == FALSE
                        )
                    )
                ) {
                    throw new BadRequestHttpException('Year or forthcoming is required.');
                }
                $changes['mini'] = true;
                $this->dbs->updateYear($id, $data->year);
            }
            if (property_exists($data, 'forthcoming')) {
                if (!is_bool($data->forthcoming)) {
                    throw new BadRequestHttpException('Incorrect forthcoming data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateForthcoming($id, $data->forthcoming);
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
            if (property_exists($data, 'bookSeries')) {
                if ((
                        !is_object($data->bookSeries)
                        || !property_exists($data->bookSeries, 'id')
                        || !is_numeric($data->bookSeries->id)
                        || empty($data->bookSeries->id)
                    )
                    && !(empty($data->bookSeries))
                ) {
                    throw new BadRequestHttpException('Incorrect series data.');
                }
                $changes['full'] = true;
                $this->dbs->updateSeries($id, empty($data->bookSeries) ? null : $data->bookSeries->id);
            }
            if (property_exists($data, 'seriesVolume')) {
                if (!empty($data->seriesVolume) && !is_string($data->seriesVolume)) {
                    throw new BadRequestHttpException('Incorrect series volume data.');
                }
                $changes['full'] = true;
                $this->dbs->updateSeriesVolume($id, $data->seriesVolume);
            }
            if (property_exists($data, 'volume')) {
                if (!empty($data->volume) && !is_string($data->volume)) {
                    throw new BadRequestHttpException('Incorrect volume data.');
                }
                $changes['full'] = true;
                $this->dbs->updateVolume($id, $data->volume);
            }
            if (property_exists($data, 'totalVolumes')) {
                if (!empty($data->totalVolumes) && !is_numeric($data->totalVolumes)) {
                    throw new BadRequestHttpException('Incorrect totalVolumes data.');
                }
                $changes['full'] = true;
                $this->dbs->updateTotalVolumes($id, $data->totalVolumes);
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
            if ($isNew) {
                $this->updateElasticByIds([$id]);
            } elseif (isset($new) && isset($old)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }

    /**
     * Merge two books
     * @param int $primaryId
     * @param int $secondaryId
     * @return Book
     * @throws InvalidArgumentException
     */
    public function merge(int $primaryId, int $secondaryId): Book
    {
        if ($primaryId == $secondaryId) {
            throw new BadRequestHttpException(
                'Books with id ' . $primaryId .' and id ' . $secondaryId . ' are identical and cannot be merged.'
            );
        }
        $primary = $this->getFull($primaryId);
        $secondary = $this->getFull($secondaryId);

        $updates = [];
        if (empty($primary->getPersonRoles()) && !empty($secondary->getPersonRoles())) {
            $roles = $this->container->get(RoleManager::class)->getByType('book');
            foreach ($roles as $role) {
                if (!empty($secondary->getPersonRoles()[$role->getSystemName()])) {
                    $updates[$role->getSystemName()] = ArrayToJson::arrayToShortJson(
                        $secondary->getPersonRoles()[$role->getSystemName()][1]
                    );
                }
            }
        }
        if (empty($primary->getCluster()) && !empty($secondary->getCluster())) {
            $updates['bookCluster'] = $secondary->getCluster()->getShortJson();
        }
        if (empty($primary->getVolume()) && !empty($secondary->getVolume())) {
            $updates['volume'] = $secondary->getVolume();
        }
        if (empty($primary->getTotalVolumes()) && !empty($secondary->getTotalVolumes())) {
            $updates['totalVolumes'] = $secondary->getTotalVolumes();
        }
        if (empty($primary->getTitle()) && !empty($secondary->getTitle())) {
            $updates['title'] = $secondary->getTitle();
        }
        if (empty($primary->getYear()) && !empty($secondary->getYear())) {
            $updates['year'] = $secondary->getYear();
        }
        if (empty($primary->getForthcoming()) && !empty($secondary->getForthcoming())) {
            $updates['forthcoming'] = $secondary->getForthcoming();
        }
        if (empty($primary->getCity()) && !empty($secondary->getCity())) {
            $updates['city'] = $secondary->getCity();
        }
        if (empty($primary->getPublisher()) && !empty($secondary->getPublisher())) {
            $updates['publisher'] = $secondary->getPublisher();
        }
        if (empty($primary->getSeries()) && !empty($secondary->getSeries())) {
            $updates['bookSeries'] = $secondary->getSeries()->getShortJson();
        }
        if (empty($primary->getSeriesVolume()) && !empty($secondary->getSeriesVolume())) {
            $updates['seriesVolume'] = $secondary->getSeriesVolume();
        }
        if (empty($primary->getPublicComment()) && !empty($secondary->getPublicComment())) {
            $updates['publicComment'] = $secondary->getPublicComment();
        }
        if (empty($primary->getPrivateComment()) && !empty($secondary->getPrivateComment())) {
            $updates['privateComment'] = $secondary->getPrivateComment();
        }

        $manuscripts = $this->container->get(ManuscriptManager::class)->getBookDependencies($secondaryId, 'getMini');
        $occurrences = $this->container->get(OccurrenceManager::class)->getBookDependencies($secondaryId, 'getMini');
        $types = $this->container->get(TypeManager::class)->getBookDependencies($secondaryId, 'getMini');
        $translations = $this->container->get(TranslationManager::class)->getBookDependencies($secondaryId, 'getMini');
        $persons = $this->container->get(PersonManager::class)->getBookDependencies($secondaryId, 'getMini');
        $bookChapters = $this->container->get(BookChapterManager::class)->getBookDependencies($secondaryId, 'getMini');

        $this->dbs->beginTransaction();
        try {
            if (!empty($updates)) {
                $primary = $this->update($primaryId, json_decode(json_encode($updates)));
            }

            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $full = $this->container->get(ManuscriptManager::class)->getFull($manuscript->getId());
                    $bibliographies = $full->getBibliographies();
                    $update = $this->getBiblioMergeUpdate($bibliographies, $primaryId, $secondaryId);
                    $this->container->get(ManuscriptManager::class)->update(
                        $manuscript->getId(),
                        json_decode(json_encode(['bibliography' => $update]))
                    );
                }
            }
            if (!empty($occurrences)) {
                foreach ($occurrences as $occurrence) {
                    $full = $this->container->get(OccurrenceManager::class)->getFull($occurrence->getId());
                    $bibliographies = $full->getBibliographies();
                    $update = $this->getBiblioMergeUpdate($bibliographies, $primaryId, $secondaryId);
                    $this->container->get(OccurrenceManager::class)->update(
                        $occurrence->getId(),
                        json_decode(json_encode(['bibliography' => $update]))
                    );
                }
            }
            if (!empty($types)) {
                foreach ($types as $type) {
                    $full = $this->container->get(TypeManager::class)->getFull($type->getId());
                    $bibliographies = $full->getBibliographies();
                    $update = $this->getBiblioMergeUpdate($bibliographies, $primaryId, $secondaryId);
                    $this->container->get(TypeManager::class)->update(
                        $type->getId(),
                        json_decode(json_encode(['bibliography' => $update]))
                    );
                }
            }
            if (!empty($translations)) {
                foreach ($translations as $translation) {
                    $full = $this->container->get(TranslationManager::class)->getFull($translation->getId());
                    $bibliographies = $full->getBibliographies();
                    $update = $this->getBiblioMergeUpdate($bibliographies, $primaryId, $secondaryId);
                    $this->container->get(TranslationManager::class)->update(
                        $translation->getId(),
                        json_decode(json_encode(['bibliography' => $update]))
                    );
                }
            }
            if (!empty($persons)) {
                foreach ($persons as $person) {
                    $full = $this->container->get(PersonManager::class)->getFull($person->getId());
                    $bibliographies = $full->getBibliographies();
                    $update = $this->getBiblioMergeUpdate($bibliographies, $primaryId, $secondaryId);
                    $this->container->get(PersonManager::class)->update(
                        $person->getId(),
                        json_decode(json_encode(['bibliography' => $update]))
                    );
                }
            }
            if (!empty($bookChapters)) {
                foreach ($bookChapters as $bookChapter) {
                    $this->container->get(BookChapterManager::class)->update(
                        $bookChapter->getId(),
                        json_decode(json_encode(['book' => ['id' => $primaryId]]))
                    );
                }
            }

            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            $this->updateElasticByIds([$primaryId]);

            $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($manuscripts));
            $this->container->get(OccurrenceManager::class)->updateElasticByIds(array_keys($occurrences));
            $this->container->get(TypeManager::class)->updateElasticByIds(array_keys($types));
            $this->container->get(PersonManager::class)->updateElasticByIds(array_keys($persons));
            $this->container->get(BookChapterManager::class)->updateElasticByIds(array_keys($bookChapters));

            throw $e;
        }

        return $primary;
    }

    /**
     * Construct the data to update a (biblio) dependent entity when merging books
     * @param  array $bibliographies
     * @param  int   $primaryId
     * @param  int   $secondaryId
     * @return array
     */
    private static function getBiblioMergeUpdate(array $bibliographies, int $primaryId, int $secondaryId): array
    {
        $update = [
            'articles' => [],
            'blogPosts' => [],
            'books' => [],
            'bookChapters' => [],
            'onlineSources' => [],
        ];
        foreach ($bibliographies as $bibliography) {
            if ($bibliography instanceof ArticleBibliography) {
                $update['articles'][] = [
                    'type' => 'article',
                    'id' => $bibliography->getId(),
                    'article' => ['id' => $bibliography->getArticle()->getId()],
                    'startPage' => $bibliography->getStartPage(),
                    'endPage' => $bibliography->getEndPage(),
                    'rawPages' => $bibliography->getRawPages(),
                    'referenceType' => $bibliography->getReferenceType() ? ['id' => $bibliography->getReferenceType()->getId()] : null,
                    'image' => $bibliography->getImage() ?? null,
                ];
            } elseif ($bibliography instanceof BlogPostBibliography) {
                $update['blogPosts'][] = [
                    'type' => 'blogPost',
                    'id' => $bibliography->getId(),
                    'blogPost' => ['id' => $bibliography->getBlogPost()->getId()],
                    'referenceType' => $bibliography->getReferenceType() ? ['id' => $bibliography->getReferenceType()->getId()] : null,
                    'image' => $bibliography->getImage() ?? null,
                ];
            } elseif ($bibliography instanceof BookBibliography) {
                $bookId = $bibliography->getBook()->getId();
                if ($bookId == $secondaryId) {
                    $bookId = $primaryId;
                }
                $update['books'][] = [
                    'type' => 'book',
                    'id' => $bibliography->getId(),
                    'book' => ['id' => $bookId],
                    'startPage' => $bibliography->getStartPage(),
                    'endPage' => $bibliography->getEndPage(),
                    'rawPages' => $bibliography->getRawPages(),
                    'referenceType' => $bibliography->getReferenceType() ? ['id' => $bibliography->getReferenceType()->getId()] : null,
                    'image' => $bibliography->getImage() ?? null,
                ];
            } elseif ($bibliography instanceof BookChapterBibliography) {
                $update['bookChapters'][] = [
                    'type' => 'bookChapter',
                    'id' => $bibliography->getId(),
                    'bookChapter' => ['id' => $bibliography->getBookChapter()->getId()],
                    'startPage' => $bibliography->getStartPage(),
                    'endPage' => $bibliography->getEndPage(),
                    'rawPages' => $bibliography->getRawPages(),
                    'referenceType' => $bibliography->getReferenceType() ? ['id' => $bibliography->getReferenceType()->getId()] : null,
                    'image' => $bibliography->getImage(),
                ];
            } elseif ($bibliography instanceof OnlineSourceBibliography) {
                $update['onlineSources'][] = [
                    'type' => 'onlineSource',
                    'id' => $bibliography->getId(),
                    'onlineSource' => ['id' => $bibliography->getOnlineSource()->getId()],
                    'relUrl' => $bibliography->getRelUrl(),
                    'referenceType' => $bibliography->getReferenceType() ? ['id' => $bibliography->getReferenceType()->getId()] : null,
                    'image' => $bibliography->getImage() ?? null,
                ];
            } elseif ($bibliography instanceof PhdBibliography) {
                $update['phds'][] = [
                    'type' => 'phd',
                    'id' => $bibliography->getId(),
                    'phd' => ['id' => $bibliography->getPhd()->getId()],
                    'referenceType' => $bibliography->getReferenceType() ? ['id' => $bibliography->getReferenceType()->getId()] : null,
                    'image' => $bibliography->getImage() ?? null,
                ];
            }
        }
        return $update;
    }
}
