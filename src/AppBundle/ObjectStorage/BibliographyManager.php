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
                $referenceTypeIds = self::getUniqueIds($rawBibliographies, 'reference_type_id');

                $articles = $this->container->get('article_manager')->getMini($articleIds);
                $books = $this->container->get('book_manager')->getMini($bookIds);
                $bookChapters = $this->container->get('book_chapter_manager')->getMini($bookChapterIds);
                $onlineSources = $this->container->get('online_source_manager')->getMini($onlineSourceIds);
                $referenceTypes = $this->container->get('reference_type_manager')->get($referenceTypeIds);

                foreach ($rawBibliographies as $rawBibliography) {
                    switch ($rawBibliography['bib_type']) {
                        case 'article':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new ArticleBibliography($rawBibliography['reference_id']))
                                    ->setArticle($articles[$rawBibliography['source_id']])
                                    ->setStartPage($rawBibliography['page_start'])
                                    ->setEndPage($rawBibliography['page_end'])
                                    ->setRawPages($rawBibliography['raw_pages']);
                            break;
                        case 'book':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new BookBibliography($rawBibliography['reference_id']))
                                    ->setBook($books[$rawBibliography['source_id']])
                                    ->setStartPage($rawBibliography['page_start'])
                                    ->setEndPage($rawBibliography['page_end'])
                                    ->setRawPages($rawBibliography['raw_pages']);
                            break;
                        case 'book_chapter':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new BookChapterBibliography($rawBibliography['reference_id']))
                                    ->setBookChapter($bookChapters[$rawBibliography['source_id']])
                                    ->setStartPage($rawBibliography['page_start'])
                                    ->setEndPage($rawBibliography['page_end'])
                                    ->setRawPages($rawBibliography['raw_pages']);
                            break;
                        case 'online_source':
                            $bibliographies[$rawBibliography['reference_id']] =
                                (new OnlineSourceBibliography($rawBibliography['reference_id']))
                                    ->setOnlineSource($onlineSources[$rawBibliography['source_id']])
                                    ->setRelUrl($rawBibliography['rel_url']);
                            break;
                    }
                    if (!empty($rawBibliography['reference_type_id'])) {
                        $bibliographies[$rawBibliography['reference_id']]
                            ->setReferenceType($referenceTypes[$rawBibliography['reference_type_id']]);
                    }
                    if (!empty($rawBibliography['source_remark'])) {
                        $bibliographies[$rawBibliography['reference_id']]
                            ->setSourceRemark($rawBibliography['source_remark']);
                    }
                    if (!empty($rawBibliography['note'])) {
                        $bibliographies[$rawBibliography['reference_id']]
                            ->setNote($rawBibliography['note']);
                    }
                }

                return $bibliographies;
            }
        );
    }

    public function add(
        int $targetId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $relUrl = null,
        int $referenceTypeId = null,
        string $sourceRemark = null,
        string $note = null
    ): Bibliography {
        $id = $this->dbs->insert(
            $targetId,
            $sourceId,
            $startPage,
            $endPage,
            $relUrl,
            $referenceTypeId,
            $sourceRemark,
            $note
        );
        return $this->get([$id])[$id];
    }

    public function update(
        int $id,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $rawPages = null,
        string $relUrl = null,
        int $referenceTypeId = null,
        string $sourceRemark = null,
        string $note = null
    ): Bibliography {
        $this->deleteCache(Bibliography::CACHENAME, $id);
        $this->dbs->update(
            $id,
            $sourceId,
            $startPage,
            $endPage,
            $rawPages,
            $relUrl,
            $referenceTypeId,
            $sourceRemark,
            $note
        );
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
