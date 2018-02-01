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
    public function getBookBibliographiesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'book_bibliography');
        if (empty($ids)) {
            return $cached;
        }

        $bibliographies = [];
        $rawBibliographies = $this->dbs->getBookBibliographiesByIds($ids);

        $personIds = self::getUniqueIds($rawBibliographies, 'person_id');
        $persons = $this->oms['person_manager']->getPersonsByIds($personIds);

        // The query result can contain multiple rows for each bibliography
        // if there are multiple authors.
        foreach ($rawBibliographies as $rawBibliography) {
            if (!array_key_exists($rawBibliography['reference_id'], $bibliographies)) {
                $bibliographies[$rawBibliography['reference_id']] =
                    (new BookBibliography($rawBibliography['reference_id']))
                        ->setBook(
                            new Book(
                                $rawBibliography['book_id'],
                                $rawBibliography['year'],
                                $rawBibliography['title'],
                                $rawBibliography['city'],
                                null
                            )
                        )
                        ->setStartPage($rawBibliography['page_start'])
                        ->setEndPage($rawBibliography['page_end'])
                        ->addCacheDependency('book.' . $rawBibliography['book_id']);
            }
            $bibliographies[$rawBibliography['reference_id']]
                ->addCacheDependency('person.' . $rawBibliography['person_id'])
                ->getBook()
                    ->addAuthor($persons[$rawBibliography['person_id']]);
        }

        $this->setCache($bibliographies, 'book_bibliography');

        return $cached + $bibliographies;
    }

    public function getArticleBibliographiesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'article_bibliography');
        if (empty($ids)) {
            return $cached;
        }

        $bibliographies = [];
        $rawBibliographies = $this->dbs->getArticleBibliographiesByIds($ids);

        $personIds = self::getUniqueIds($rawBibliographies, 'person_id');
        $persons = $this->oms['person_manager']->getPersonsByIds($personIds);

        // The query result can contain multiple rows for each bibliography
        // if there are multiple authors.
        foreach ($rawBibliographies as $rawBibliography) {
            if (!array_key_exists($rawBibliography['reference_id'], $bibliographies)) {
                $bibliographies[$rawBibliography['reference_id']] =
                    (new ArticleBibliography($rawBibliography['reference_id']))
                        ->setArticle(
                            (new Article(
                                $rawBibliography['article_id'],
                                $rawBibliography['article_title'],
                                new Journal(
                                    $rawBibliography['journal_id'],
                                    $rawBibliography['journal_title'],
                                    $rawBibliography['journal_year'],
                                    $rawBibliography['journal_volume'],
                                    $rawBibliography['journal_number']
                                )
                            ))
                            ->setStartPage($rawBibliography['article_page_start'])
                            ->setEndPage($rawBibliography['article_page_end'])
                        )
                        ->setStartPage($rawBibliography['page_start'])
                        ->setEndPage($rawBibliography['page_end'])
                        ->addCacheDependency('article.' . $rawBibliography['article_id'])
                        ->addCacheDependency('journal.' . $rawBibliography['journal_id']);
            }
            $bibliographies[$rawBibliography['reference_id']]
                ->addCacheDependency('person.' . $rawBibliography['person_id'])
                ->getArticle()
                    ->addAuthor($persons[$rawBibliography['person_id']]);
        }

        $this->setCache($bibliographies, 'article_bibliography');

        return $cached + $bibliographies;
    }

    public function getBookChapterBibliographiesByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'book_chapter_bibliography');
        if (empty($ids)) {
            return $cached;
        }

        $bibliographies = [];
        $rawBibliographies = $this->dbs->getBookChapterBibliographiesByIds($ids);

        $personIds = self::getUniqueIds($rawBibliographies, 'person_id');
        $persons = $this->oms['person_manager']->getPersonsByIds($personIds);

        // The query result can contain multiple rows for each bibliography
        // if there are multiple authors.
        foreach ($rawBibliographies as $rawBibliography) {
            if (!array_key_exists($rawBibliography['reference_id'], $bibliographies)) {
                $bibliographies[$rawBibliography['reference_id']] =
                    (new BookChapterBibliography($rawBibliography['reference_id']))
                        ->setBookChapter(
                            (new BookChapter(
                                $rawBibliography['book_chapter_id'],
                                $rawBibliography['book_chapter_title'],
                                new Book(
                                    $rawBibliography['book_id'],
                                    $rawBibliography['book_year'],
                                    $rawBibliography['book_title'],
                                    $rawBibliography['book_city'],
                                    $rawBibliography['book_editor']
                                )
                            ))
                            ->setStartPage($rawBibliography['book_chapter_page_start'])
                            ->setEndPage($rawBibliography['book_chapter_page_end'])
                        )
                        ->setStartPage($rawBibliography['page_start'])
                        ->setEndPage($rawBibliography['page_end'])
                        ->addCacheDependency('book_chapter.' . $rawBibliography['book_chapter_id'])
                        ->addCacheDependency('book.' . $rawBibliography['book_id']);
            }
            $bibliographies[$rawBibliography['reference_id']]
                ->addCacheDependency('person.' . $rawBibliography['person_id'])
                ->getBookChapter()
                    ->addAuthor($persons[$rawBibliography['person_id']]);
        }

        // Add authors
        foreach ($rawBibliographies as $rawBibliography) {
            foreach ($bibliographies as $bibliography) {
            }
        }

        $this->setCache($bibliographies, 'book_chapter_bibliography');

        return $cached + $bibliographies;
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
