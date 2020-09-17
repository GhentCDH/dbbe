<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Bibliography;
use AppBundle\Model\ArticleBibliography;
use AppBundle\Model\BibVariaBibliography;
use AppBundle\Model\BlogPostBibliography;
use AppBundle\Model\BookBibliography;
use AppBundle\Model\BookChapterBibliography;
use AppBundle\Model\OnlineSourceBibliography;
use AppBundle\Model\PhdBibliography;

class BibliographyManager extends ObjectManager
{
    public function get(array $ids): array
    {
        $bibliographies = [];
        $rawBibliographies = $this->dbs->getBibliographiesByIds($ids);

        $articleIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'article');
        $blogPostIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'blog_post');
        $bookIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'book');
        $bookChapterIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'book_chapter');
        $onlineSourceIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'online_source');
        $phdIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'phd');
        $bibVariaIds = self::getUniqueIds($rawBibliographies, 'source_id', 'bib_type', 'bib_varia');
        $referenceTypeIds = self::getUniqueIds($rawBibliographies, 'reference_type_id');

        $articles = $this->container->get('article_manager')->getMini($articleIds);
        $blogPosts = $this->container->get('blog_post_manager')->getMini($blogPostIds);
        $books = $this->container->get('book_manager')->getMini($bookIds);
        $bookChapters = $this->container->get('book_chapter_manager')->getMini($bookChapterIds);
        $onlineSources = $this->container->get('online_source_manager')->getMini($onlineSourceIds);
        $phds = $this->container->get('phd_manager')->getMini($phdIds);
        $bibVarias = $this->container->get('bib_varia_manager')->getMini($bibVariaIds);
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
                case 'blog_post':
                    $bibliographies[$rawBibliography['reference_id']] =
                        (new BlogPostBibliography($rawBibliography['reference_id']))
                            ->setBlogPost($blogPosts[$rawBibliography['source_id']]);
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
                case 'phd':
                    $bibliographies[$rawBibliography['reference_id']] =
                        (new PhdBibliography($rawBibliography['reference_id']))
                            ->setPhd($phds[$rawBibliography['source_id']])
                            ->setStartPage($rawBibliography['page_start'])
                            ->setEndPage($rawBibliography['page_end'])
                            ->setRawPages($rawBibliography['raw_pages']);
                    break;
                case 'bib_varia':
                    $bibliographies[$rawBibliography['reference_id']] =
                        (new BibVariaBibliography($rawBibliography['reference_id']))
                            ->setBibVaria($bibVarias[$rawBibliography['source_id']])
                            ->setStartPage($rawBibliography['page_start'])
                            ->setEndPage($rawBibliography['page_end'])
                            ->setRawPages($rawBibliography['raw_pages']);
                    break;
            }
            if (!empty($rawBibliography['reference_type_id'])) {
                $bibliographies[$rawBibliography['reference_id']]
                    ->setReferenceType($referenceTypes[$rawBibliography['reference_type_id']]);
            }
            if (!empty($rawBibliography['image'])) {
                $bibliographies[$rawBibliography['reference_id']]
                    ->setImage($rawBibliography['image']);
            }
        }

        return $bibliographies;
    }

    public function add(
        int $targetId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $relUrl = null,
        int $referenceTypeId = null,
        string $image = null
    ): Bibliography {
        $id = $this->dbs->insert(
            $targetId,
            $sourceId,
            $startPage,
            $endPage,
            $relUrl,
            $referenceTypeId,
            $image
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
        string $image = null
    ): Bibliography {
        $this->dbs->update(
            $id,
            $sourceId,
            $startPage,
            $endPage,
            $rawPages,
            $relUrl,
            $referenceTypeId,
            $image
        );
        return $this->get([$id])[$id];
    }


    public function deleteMultiple(array $ids): void
    {
        $this->dbs->deleteMultiple($ids);
    }
}
