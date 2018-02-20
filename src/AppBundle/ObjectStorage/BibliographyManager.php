<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Article;
use AppBundle\Model\ArticleBibliography;
use AppBundle\Model\Book;
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
        list($cached, $ids) = $this->getCache($ids, 'book_bibliography');
        if (empty($ids)) {
            return $cached;
        }

        $bibliographies = [];
        $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

        $bookIds = self::getUniqueIds($rawBibliographies, 'source_id');
        $books = $this->getBooksByIds($bookIds);

        foreach ($rawBibliographies as $rawBibliography) {
            $bibliography =
                (new BookBibliography($rawBibliography['reference_id']))
                    ->setBook($books[$rawBibliography['source_id']])
                    ->addCacheDependency('book.' . $rawBibliography['source_id'])
                    ->setStartPage($rawBibliography['page_start'])
                    ->setEndPage($rawBibliography['page_end']);
            foreach ($books[$rawBibliography['source_id']]->getCacheDependencies() as $cacheDependency) {
                $bibliography->addCacheDePendency($cacheDependency);
            }

            $bibliographies[$bibliography->getId()] = $bibliography;
        }

        $this->setCache($bibliographies, 'book_bibliography');

        return $cached + $bibliographies;
    }

    public function getBooksByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'book');
        if (empty($ids)) {
            return $cached;
        }

        $books = [];
        $rawBooks = $this->dbs->getBooksByIds($ids);

        $personIds = self::getUniqueIds($rawBooks, 'person_ids');
        $persons = $this->oms['person_manager']->getPersonsByIds($personIds);

        foreach ($rawBooks as $rawBook) {
            $book = new Book(
                $rawBook['book_id'],
                $rawBook['year'],
                $rawBook['title'],
                $rawBook['city'],
                $rawBook['editor']
            );
            foreach (json_decode($rawBook['person_ids']) as $personId) {
                if (!empty($personId)) {
                    $book
                        ->addAuthor($persons[$personId])
                        ->addCacheDependency('person.' . $personId);
                }
            }

            $books[$rawBook['book_id']] = $book;
        }

        $this->setCache($books, 'book');

        return $cached + $books;
    }

    public function getAllBooks(): array
    {
        $cache = $this->cache->getItem('books');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getBookIds();
        $ids = self::getUniqueIds($rawIds, 'book_id');
        $books = $this->getBooksByIds($ids);

        // Sort by description
        usort($books, function ($a, $b) {
            return strcmp($a->getDescription(), $b->getDescription());
        });

        $cache->tag('books');
        $this->cache->save($cache->set($books));
        return $books;
    }

    public function getArticleBibliographiesByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'article_bibliography');
        if (empty($ids)) {
            return $cached;
        }

        $bibliographies = [];
        $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

        $articleIds = self::getUniqueIds($rawBibliographies, 'source_id');
        $articles = $this->getArticlesByIds($articleIds);

        foreach ($rawBibliographies as $rawBibliography) {
            $bibliography =
                (new ArticleBibliography($rawBibliography['reference_id']))
                    ->setArticle($articles[$rawBibliography['source_id']])
                    ->addCacheDependency('article.' . $rawBibliography['source_id'])
                    ->setStartPage($rawBibliography['page_start'])
                    ->setEndPage($rawBibliography['page_end']);
            foreach ($articles[$rawBibliography['source_id']]->getCacheDependencies() as $cacheDependency) {
                $bibliography->addCacheDePendency($cacheDependency);
            }

            $bibliographies[$bibliography->getId()] = $bibliography;
        }

        $this->setCache($bibliographies, 'article_bibliography');

        return $cached + $bibliographies;
    }

    public function getArticlesByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'article');
        if (empty($ids)) {
            return $cached;
        }

        $articles = [];
        $rawArticles = $this->dbs->getArticlesByIds($ids);

        $journalIds = self::getUniqueIds($rawArticles, 'journal_id');
        $journals = $this->getJournalsByIds($journalIds);

        $personIds = self::getUniqueIds($rawArticles, 'person_ids');
        $persons = $this->oms['person_manager']->getPersonsByIds($personIds);

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
                    $article
                        ->addAuthor($persons[$personId])
                        ->addCacheDependency('person.' . $personId);
                }
            }

            $articles[$rawArticle['article_id']] = $article;
        }

        $this->setCache($articles, 'article');

        return $cached + $articles;
    }

    public function getJournalsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'journal');
        if (empty($ids)) {
            return $cached;
        }

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

        $this->setCache($journals, 'journal');

        return $cached + $journals;
    }

    public function getAllArticles(): array
    {
        $cache = $this->cache->getItem('articles');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getArticleIds();
        $ids = self::getUniqueIds($rawIds, 'article_id');
        $articles = $this->getArticlesByIds($ids);

        // Sort by description
        usort($articles, function ($a, $b) {
            return strcmp($a->getDescription(), $b->getDescription());
        });

        $cache->tag('articles');
        $this->cache->save($cache->set($articles));
        return $articles;
    }

    public function getBookChapterBibliographiesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'book_chapter_bibliography');
        if (empty($ids)) {
            return $cached;
        }

        $bibliographies = [];
        $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

        $bookChapterIds = self::getUniqueIds($rawBibliographies, 'source_id');
        $bookChapters = $this->getBookChaptersByIds($bookChapterIds);

        foreach ($rawBibliographies as $rawBibliography) {
            $bibliography =
                (new BookChapterBibliography($rawBibliography['reference_id']))
                    ->setBookChapter($bookChapters[$rawBibliography['source_id']])
                    ->addCacheDependency('book_chapter.' . $rawBibliography['source_id'])
                    ->setStartPage($rawBibliography['page_start'])
                    ->setEndPage($rawBibliography['page_end']);
            foreach ($bookChapters[$rawBibliography['source_id']]->getCacheDependencies() as $cacheDependency) {
                $bibliography->addCacheDePendency($cacheDependency);
            }

            $bibliographies[$bibliography->getId()] = $bibliography;
        }

        $this->setCache($bibliographies, 'book_chapter_bibliography');

        return $cached + $bibliographies;
    }

    public function getBookChaptersByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'book_chapter');
        if (empty($ids)) {
            return $cached;
        }

        $bookChapters = [];
        $rawBookChapters = $this->dbs->getBookChaptersByIds($ids);

        $bookIds = self::getUniqueIds($rawBookChapters, 'book_id');
        $books = $this->getBooksByIds($bookIds);

        $personIds = self::getUniqueIds($rawBookChapters, 'person_ids');
        $persons = $this->oms['person_manager']->getPersonsByIds($personIds);

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
                    $bookChapter
                        ->addAuthor($persons[$personId])
                        ->addCacheDependency('person.' . $personId);
                }
            }

            $bookChapters[$rawBookChapter['book_chapter_id']] = $bookChapter;
        }

        $this->setCache($bookChapters, 'book_chapter');

        return $cached + $bookChapters;
    }

    public function getAllBookChapters(): array
    {
        $cache = $this->cache->getItem('book_chapters');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getBookChapterIds();
        $ids = self::getUniqueIds($rawIds, 'book_chapter_id');
        $bookChapters = $this->getBookChaptersByIds($ids);

        // Sort by description
        usort($bookChapters, function ($a, $b) {
            return strcmp($a->getDescription(), $b->getDescription());
        });

        $cache->tag('book_chapters');
        $this->cache->save($cache->set($bookChapters));
        return $bookChapters;
    }

    public function getOnlineSourceBibliographiesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'online_source_bibliography');
        if (empty($ids)) {
            return $cached;
        }

        $rawBibliographies = $this->dbs->getOnlineSourceBibliographiesByIds($ids);

        foreach ($rawBibliographies as $rawBibliography) {
            $bibliography = (new OnlineSourceBibliography($rawBibliography['reference_id']))
                ->setOnlineSource(
                    new OnlineSource(
                        $rawBibliography['online_source_id'],
                        $rawBibliography['base_url'],
                        $rawBibliography['institution_name'],
                        $rawBibliography['last_accessed']
                    )
                )
                ->setRelUrl($rawBibliography['rel_url']);

            $bibliography
                ->addCacheDependency('online_source.' . $rawBibliography['online_source_id']);

            $bibliographies[$bibliography->getId()] = $bibliography;
        }

        $this->setCache($bibliographies, 'online_source_bibliography');

        return $cached + $bibliographies;
    }
}
