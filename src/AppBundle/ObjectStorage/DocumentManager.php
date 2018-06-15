<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Document;
use AppBundle\Model\FuzzyDate;

class DocumentManager extends ObjectManager
{
    protected function setPublics(array &$documents): void
    {
        $ids = array_map(function ($document) {
            return $document->getId();
        }, $documents);
        $rawPublics = $this->dbs->getPublics($ids);
        foreach ($rawPublics as $rawPublic) {
            $documents[$rawPublic['document_id']]
                // default: true (if no value is set in the database)
                ->setPublic(isset($rawPublic['public']) ? $rawPublic['public'] : true);
        }
    }

    protected function setDates(array &$documents): void
    {
        $ids = array_map(function ($document) {
            return $document->getId();
        }, $documents);
        $rawCompletionDates = $this->dbs->getCompletionDates($ids);
        foreach ($rawCompletionDates as $rawCompletionDate) {
            $documents[$rawCompletionDate['document_id']]
                ->setDate(new FuzzyDate($rawCompletionDate['completion_date']));
        }
    }

    protected function setComments(array &$documents): void
    {
        $ids = array_map(function ($document) {
            return $document->getId();
        }, $documents);
        $rawComments = $this->dbs->getComments($ids);
        foreach ($rawComments as $rawComment) {
            $documents[$rawComment['document_id']]
                ->setPublicComment($rawComment['public_comment'])
                ->setPrivateComment($rawComment['private_comment']);
        }
    }

    protected function setPrevIds(array &$documents): void
    {
        $ids = array_map(function ($document) {
            return $document->getId();
        }, $documents);
        $rawPrevIds = $this->dbs->getPrevIds($ids);
        foreach ($rawPrevIds as $rawPrevId) {
            $documents[$rawPrevId['document_id']]
                ->setPrevId($rawPrevId['prev_id']);
        }
    }

    protected function setBibliographies(array &$documents): void
    {
        $ids = array_map(function ($document) {
            return $document->getId();
        }, $documents);
        $rawBibliographies = $this->dbs->getBibliographies($ids);
        if (!empty($rawBibliographies)) {
            $bookIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book');
            $articleIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'article');
            $bookChapterIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'book_chapter');
            $onlineSourceIds = self::getUniqueIds($rawBibliographies, 'reference_id', 'type', 'online_source');

            $bookBibliographies = $this->container->get('bibliography_manager')->getBookBibliographiesByIds($bookIds);
            $articleBibliographies = $this->container->get('bibliography_manager')->getArticleBibliographiesByIds($articleIds);
            $bookChapterBibliographies = $this->container->get('bibliography_manager')->getBookChapterBibliographiesByIds($bookChapterIds);
            $onlineSourceBibliographies = $this->container->get('bibliography_manager')->getOnlineSourceBibliographiesByIds($onlineSourceIds);

            $bibliographies = $bookBibliographies + $articleBibliographies + $bookChapterBibliographies + $onlineSourceBibliographies;

            foreach ($rawBibliographies as $rawBibliography) {
                $biblioId = $rawBibliography['reference_id'];
                // Add cache dependencies
                switch ($rawBibliography['type']) {
                    case 'book':
                        $documents[$rawBibliography['document_id']]
                            ->addCacheDependency('book_bibliography.' . $biblioId);
                        break;
                    case 'article':
                        $documents[$rawBibliography['document_id']]
                            ->addCacheDependency('article_bibliography.' . $biblioId);
                        break;
                    case 'book_chapter':
                        $documents[$rawBibliography['document_id']]
                            ->addCacheDependency('book_chapter_bibliography.' . $biblioId);
                        break;
                    case 'online_source':
                        $documents[$rawBibliography['document_id']]
                            ->addCacheDependency('online_source_bibliography.' . $biblioId);
                        break;
                }
                // Add cache dependency dependencies
                foreach ($bibliographies[$biblioId]->getCacheDependencies() as $cacheDependency) {
                    $documents[$rawBibliography['document_id']]
                        ->addCacheDependency($cacheDependency);
                }
                // Add to document
                $documents[$rawBibliography['document_id']]
                    ->addBibliography($bibliographies[$biblioId]);
            }
        }
    }
}
