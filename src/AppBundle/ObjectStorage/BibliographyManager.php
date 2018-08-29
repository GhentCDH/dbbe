<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Bibliography;
use AppBundle\Model\ArticleBibliography;
use AppBundle\Model\BookBibliography;
use AppBundle\Model\BookChapterBibliography;
use AppBundle\Model\OnlineSourceBibliography;

class BibliographyManager extends ObjectManager
{
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Bibliography::CACHENAME,
            $ids,
            function ($ids) {
                $bibliographies = [];
                $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

                $articleIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'article');
                $bookIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'book');
                $bookChapterIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'book_chapter');
                $onlineSourceIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'online_source');

                $articles = $this->container->get('article_manager')->getMini($articleIds);
                $books = $this->container->get('book_manager')->getMini($bookIds);
                $bookChapters = $this->container->get('book_chapter_manager')->getMini($bookChapterIds);
                $onlineSources = $this->container->get('online_source_manager')->getMini($onlineSourceIds);

                foreach ($rawBibliographies as $rawBibliography) {
                    switch ($rawBibliography['bib_type']) {
                        case 'article':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new ArticleBibliography($rawBibliography['reference_id']))
                                    ->setArticle($articles[$rawBibliography['source_id']])
                                    ->setStartPage($rawBibliography['page_start'])
                                    ->setEndPage($rawBibliography['page_end'])
                                    ->setRawPages($rawBibliography['raw_pages'])
                                    ->setRefType($rawBibliography['ref_type']);
                            break;
                        case 'book':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new BookBibliography($rawBibliography['reference_id']))
                                    ->setBook($books[$rawBibliography['source_id']])
                                    ->setStartPage($rawBibliography['page_start'])
                                    ->setEndPage($rawBibliography['page_end'])
                                    ->setRawPages($rawBibliography['raw_pages'])
                                    ->setRefType($rawBibliography['ref_type']);
                            break;
                        case 'book_chapter':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new BookChapterBibliography($rawBibliography['reference_id']))
                                    ->setBookChapter($bookChapters[$rawBibliography['source_id']])
                                    ->setStartPage($rawBibliography['page_start'])
                                    ->setEndPage($rawBibliography['page_end'])
                                    ->setRawPages($rawBibliography['raw_pages'])
                                    ->setRefType($rawBibliography['ref_type']);
                            break;
                        case 'online_source':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new OnlineSourceBibliography($rawBibliography['reference_id']))
                                    ->setOnlineSource($onlineSources[$rawBibliography['source_id']])
                                    ->setRelUrl($rawBibliography['rel_url'])
                                    ->setRefType($rawBibliography['ref_type']);
                    }
                }

                return $bibliographies;
            }
        );
    }

    public function add(int $targetId, int $sourceId, string $startPage = null, string $endPage = null, string $relUrl = null): Bibliography
    {
        $id = $this->dbs->insert($targetId, $sourceId, $startPage, $endPage, $relUrl);
        return $this->get([$id])[$id];
    }

    public function update(int $id, int $sourceId, string $startPage = null, string $endPage = null, string $rawPages = null, string $relUrl = null): Bibliography
    {
        $this->deleteCache(Bibliography::CACHENAME, $bibliographyId);
        $this->dbs->update($id, $sourceId, $startPage, $endPage, $rawPages, $relUrl);
        return $this->get([$id])[$id];
    }


    public function deleteMultiple(array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteCache(Bibliography::CACHENAME, $id);
        }

        $this->dbs->deleteMultiple($ids);
    }
}
