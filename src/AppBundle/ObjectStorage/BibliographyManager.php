<?php

namespace AppBundle\ObjectStorage;

use DateTime;

use AppBundle\Model\Article;
use AppBundle\Model\ArticleBibliography;
use AppBundle\Model\BookBibliography;
use AppBundle\Model\BookChapter;
use AppBundle\Model\BookChapterBibliography;
use AppBundle\Model\OnlineSource;
use AppBundle\Model\OnlineSourceBibliography;

class BibliographyManager extends ObjectManager
{
    public function getArticleBibliographiesByIds(array $ids): array
    {
        return $this->wrapCache(
            ArticleBibliography::CACHENAME,
            $ids,
            function ($ids) {
                $bibliographies = [];
                $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

                $articleIds = self::getUniqueIds($rawBibliographies, 'source_id');
                $articles = $this->container->get('article_manager')->getMini($articleIds);

                foreach ($rawBibliographies as $rawBibliography) {
                    $bibliography =
                        (new ArticleBibliography($rawBibliography['reference_id']))
                            ->setArticle($articles[$rawBibliography['source_id']])
                            ->setStartPage($rawBibliography['page_start'])
                            ->setEndPage($rawBibliography['page_end'])
                            ->setRawPages($rawBibliography['raw_pages'])
                            ->setRefType($rawBibliography['type']);

                    $bibliographies[$bibliography->getId()] = $bibliography;
                }

                return $bibliographies;
            }
        );
    }

    public function addArticleBibliography(int $documentId, int $articleId, string $startPage, string $endPage): void
    {
        $this->cache->invalidateTags(['article_bibliographies']);
        $this->dbs->addBibliography($documentId, $articleId, $startPage, $endPage, null);
    }

    public function updateArticleBibliography(int $bibliographyId, int $articleId, string $startPage, string $endPage, string $rawPages): void
    {
        $this->deleteCache(Article::CACHENAME, $bibliographyId);
        $this->dbs->updateBibliography($bibliographyId, $articleId, $startPage, $endPage, $rawPages, null);
    }

    public function getBookBibliographiesByIds(array $ids): array
    {
        return $this->wrapCache(
            BookBibliography::CACHENAME,
            $ids,
            function ($ids) {
                $bibliographies = [];
                $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

                $bookIds = self::getUniqueIds($rawBibliographies, 'source_id');
                $books = $this->container->get('book_manager')->getMini($bookIds);

                foreach ($rawBibliographies as $rawBibliography) {
                    $bibliography =
                        (new BookBibliography($rawBibliography['reference_id']))
                            ->setBook($books[$rawBibliography['source_id']])
                            ->setStartPage($rawBibliography['page_start'])
                            ->setEndPage($rawBibliography['page_end'])
                            ->setRawPages($rawBibliography['raw_pages'])
                            ->setRefType($rawBibliography['type']);

                    $bibliographies[$bibliography->getId()] = $bibliography;
                }

                return $bibliographies;
            }
        );
    }

    public function addBookBibliography(int $documentId, int $bookId, string $startPage, string $endPage): void
    {
        $this->cache->invalidateTags(['book_bibliographies']);
        $this->dbs->addBibliography($documentId, $bookId, $startPage, $endPage, null);
    }

    public function updateBookBibliography(int $bibliographyId, int $bookId, string $startPage, string $endPage, string $rawPages): void
    {
        $this->deleteCache(BookBibliography::CACHENAME, $bibliographyId);
        $this->dbs->updateBibliography($bibliographyId, $bookId, $startPage, $endPage, $rawPages, null);
    }

    public function getBookChapterBibliographiesByIds(array $ids): array
    {
        return $this->wrapCache(
            BookChapterBibliography::CACHENAME,
            $ids,
            function ($ids) {
                $bibliographies = [];
                $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

                $bookChapterIds = self::getUniqueIds($rawBibliographies, 'source_id');
                $bookChapters = $this->container->get('book_chapter_manager')->getMini($bookChapterIds);

                foreach ($rawBibliographies as $rawBibliography) {
                    $bibliography =
                        (new BookChapterBibliography($rawBibliography['reference_id']))
                            ->setBookChapter($bookChapters[$rawBibliography['source_id']])
                            ->setStartPage($rawBibliography['page_start'])
                            ->setEndPage($rawBibliography['page_end'])
                            ->setRawPages($rawBibliography['raw_pages'])
                            ->setRefType($rawBibliography['type']);

                    $bibliographies[$bibliography->getId()] = $bibliography;
                }

                return $bibliographies;
            }
        );
    }

    public function addBookChapterBibliography(int $documentId, int $bookChapterId, string $startPage, string $endPage): void
    {
        $this->cache->invalidateTags(['book_chapter_bibliographies']);
        $this->dbs->addBibliography($documentId, $bookChapterId, $startPage, $endPage, null);
    }

    public function updateBookChapterBibliography(int $bibliographyId, int $bookChapterId, string $startPage, string $endPage, string $rawPages): void
    {
        $this->deleteCache(BookChapterBibliography::CACHENAME, $bibliographyId);
        $this->dbs->updateBibliography($bibliographyId, $bookChapterId, $startPage, $endPage, $rawPages, null);
    }

    public function getOnlineSourceBibliographiesByIds(array $ids): array
    {
        return $this->wrapCache(
            OnlineSourceBibliography::CACHENAME,
            $ids,
            function ($ids) {
                $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

                $onlineSourceIds = self::getUniqueIds($rawBibliographies, 'source_id');
                $onlineSources = $this->getOnlineSourcesByIds($onlineSourceIds);

                foreach ($rawBibliographies as $rawBibliography) {
                    $bibliography = (new OnlineSourceBibliography($rawBibliography['reference_id']))
                        ->setOnlineSource($onlineSources[$rawBibliography['source_id']])
                        ->setRelUrl($rawBibliography['rel_url'])
                        ->setRefType($rawBibliography['type']);

                    $bibliographies[$bibliography->getId()] = $bibliography;
                }

                return $bibliographies;
            }
        );
    }

    public function addOnlineSourceBibliography(int $documentId, int $onlineSourceId, string $url): void
    {
        $this->cache->invalidateTags(['online_source_bibliographies']);
        $this->dbs->addBibliography($documentId, $onlineSourceId, null, null, $url);
    }

    public function updateOnlineSourceBibliography(int $bibliographyId, int $onlineSourceId, string $url): void
    {
        $this->deleteCache(OnlineSourceBibliography::CACHENAME, $bibliographyId);
        $this->dbs->updateBibliography($bibliographyId, $onlineSourceId, null, null, null, $url);
    }

    public function delBibliographies(array $bibliographies): void
    {
        foreach ($bibliographies as $bibliographyId => $bibliography) {
            switch ($bibliography->getType()) {
                case 'book':
                    $this->cache->invalidateTags(['book_bibliographies']);
                    $this->deleteCache(BookBibliography::CACHENAME, $bibliographyId);
                    break;
                case 'article':
                    $this->cache->invalidateTags(['article_bibliographies']);
                    $this->deleteCache(ArticleBibliography::CACHENAME, $bibliographyId);
                    break;
                case 'bookChapter':
                    $this->cache->invalidateTags(['book_chapter_bibliographies']);
                    $this->deleteCache(BookChapterBibliography::CACHENAME, $bibliographyId);
                    break;
                case 'onlineSource':
                    $this->cache->invalidateTags(['online_source_bibliographies']);
                    $this->deleteCache(OnlineSourceBibliography::CACHENAME, $bibliographyId);
                    break;
            }
        }
        $this->dbs->delBibliographies(array_keys($bibliographies));
    }
}
