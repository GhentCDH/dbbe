<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Article;
use AppBundle\Model\ArticleBibliography;
use AppBundle\Model\BookBibliography;
use AppBundle\Model\BookChapter;
use AppBundle\Model\BookChapterBibliography;
use AppBundle\Model\Journal;
use AppBundle\Model\OnlineSource;
use AppBundle\Model\OnlineSourceBibliography;

class BibliographyManager extends ObjectManager
{
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

    public function getArticleBibliographiesByIds(array $ids): array
    {
        return $this->wrapCache(
            ArticleBibliography::CACHENAME,
            $ids,
            function ($ids) {
                $bibliographies = [];
                $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

                $articleIds = self::getUniqueIds($rawBibliographies, 'source_id');
                $articles = $this->getArticlesByIds($articleIds);

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

    public function getArticlesByIds(array $ids): array
    {
        return $this->wrapCache(
            Article::CACHENAME,
            $ids,
            function ($ids) {
                $articles = [];
                $rawArticles = $this->dbs->getArticlesByIds($ids);

                $journalIds = self::getUniqueIds($rawArticles, 'journal_id');
                $journals = $this->getJournalsByIds($journalIds);

                $personIds = self::getUniqueIds($rawArticles, 'person_ids');
                $persons = $this->container->get('person_manager')->getMini($personIds);

                foreach ($rawArticles as $rawArticle) {
                    $article = (new Article(
                        $rawArticle['article_id'],
                        $rawArticle['article_title'],
                        $journals[$rawArticle['journal_id']]
                    ))
                        ->setStartPage($rawArticle['article_page_start'])
                        ->setEndPage($rawArticle['article_page_end']);
                    foreach (json_decode($rawArticle['person_ids']) as $personId) {
                        if (!empty($personId)) {
                            $article->addAuthor($persons[$personId]);
                        }
                    }

                    $articles[$rawArticle['article_id']] = $article;
                }

                return $articles;
            }
        );
    }

    public function getJournalsByIds(array $ids): array
    {
        return $this->wrapCache(
            Journal::CACHENAME,
            $ids,
            function ($ids) {
                $journals = [];
                $rawJournals = $this->dbs->getJournalsByIds($ids);

                foreach ($rawJournals as $rawJournal) {
                    $journals[$rawJournal['journal_id']] = new Journal(
                        $rawJournal['journal_id'],
                        $rawJournal['title'],
                        $rawJournal['year'],
                        $rawJournal['volume'],
                        $rawJournal['number']
                    );
                }

                return $journals;
            }
        );
    }

    public function getAllArticles(): array
    {
        return $this->wrapArrayCache(
            'articles',
            ['articles'],
            function () {
                $rawIds = $this->dbs->getArticleIds();
                $ids = self::getUniqueIds($rawIds, 'article_id');
                $articles = $this->getArticlesByIds($ids);

                // Sort by description
                usort($articles, function ($a, $b) {
                    return strcmp($a->getDescription(), $b->getDescription());
                });

                return $articles;
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

    public function getBookChapterBibliographiesByIds(array $ids): array
    {
        return $this->wrapCache(
            BookChapterBibliography::CACHENAME,
            $ids,
            function ($ids) {
                $bibliographies = [];
                $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

                $bookChapterIds = self::getUniqueIds($rawBibliographies, 'source_id');
                $bookChapters = $this->getBookChaptersByIds($bookChapterIds);

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

    public function getBookChaptersByIds(array $ids): array
    {
        return $this->wrapCache(
            BookChapter::CACHENAME,
            $ids,
            function ($ids) {
                $bookChapters = [];
                $rawBookChapters = $this->dbs->getBookChaptersByIds($ids);

                $bookIds = self::getUniqueIds($rawBookChapters, 'book_id');
                $books = $this->container->get('book_manager')->getMini($bookIds);

                $personIds = self::getUniqueIds($rawBookChapters, 'person_ids');
                $persons = $this->container->get('person_manager')->getMini($personIds);

                foreach ($rawBookChapters as $rawBookChapter) {
                    $bookChapter = (new BookChapter(
                        $rawBookChapter['book_chapter_id'],
                        $rawBookChapter['book_chapter_title'],
                        $books[$rawBookChapter['book_id']]
                    ))
                        ->setStartPage($rawBookChapter['book_chapter_page_start'])
                        ->setEndPage($rawBookChapter['book_chapter_page_end']);
                    foreach (json_decode($rawBookChapter['person_ids']) as $personId) {
                        if (!empty($personId)) {
                            $bookChapter->addAuthor($persons[$personId]);
                        }
                    }

                    $bookChapters[$rawBookChapter['book_chapter_id']] = $bookChapter;
                }

                return $bookChapters;
            }
        );
    }

    public function getAllBookChapters(): array
    {
        return $this->wrapArrayCache(
            'book_chapters',
            ['book_chapters'],
            function () {
                $rawIds = $this->dbs->getBookChapterIds();
                $ids = self::getUniqueIds($rawIds, 'book_chapter_id');
                $bookChapters = $this->getBookChaptersByIds($ids);

                // Sort by description
                usort($bookChapters, function ($a, $b) {
                    return strcmp($a->getDescription(), $b->getDescription());
                });

                return $bookChapters;
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

    public function getOnlineSourcesByIds(array $ids): array
    {
        return $this->wrapCache(
            OnlineSource::CACHENAME,
            $ids,
            function ($ids) {
                $onlineSources = [];
                $rawOnlineSources = $this->dbs->getOnlineSourcesByIds($ids);

                foreach ($rawOnlineSources as $rawOnlineSource) {
                    $onlineSources[$rawOnlineSource['online_source_id']] = new OnlineSource(
                        $rawOnlineSource['online_source_id'],
                        $rawOnlineSource['url'],
                        $rawOnlineSource['institution_name'],
                        $rawOnlineSource['last_accessed']
                    );
                }

                return $onlineSources;
            }
        );
    }

    public function getAllOnlineSources(): array
    {
        return $this->wrapArrayCache(
            'online_sources',
            ['online_sources'],
            function () {
                $rawIds = $this->dbs->getOnlineSourceIds();
                $ids = self::getUniqueIds($rawIds, 'online_source_id');
                $onlineSources = $this->getOnlineSourcesByIds($ids);

                // Sort by description
                usort($onlineSources, function ($a, $b) {
                    return strcmp($a->getDescription(), $b->getDescription());
                });

                return $onlineSources;
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
