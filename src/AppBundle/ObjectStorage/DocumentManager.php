<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Document;
use AppBundle\Model\FuzzyDate;

class DocumentManager extends ObjectManager
{
    protected function setDates(array &$documents, array $ids): void
    {
        $rawCompletionDates = $this->dbs->getCompletionDates($ids);
        foreach ($rawCompletionDates as $rawCompletionDate) {
            $documents[$rawCompletionDate['document_id']]
                ->setDate(new FuzzyDate($rawCompletionDate['completion_date']));
        }
    }

    protected function setBibliographies(Document &$document, int $id): void
    {
        // Bibliography
        $rawBibliographies = $this->dbs->getBibliographies([$id]);
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
            foreach ($bookBibliographies as $bibliography) {
                $document->addCacheDependency('book_bibliography.' . $bibliography->getId());
            }
            foreach ($articleBibliographies as $bibliography) {
                $document->addCacheDependency('article_bibliography.' . $bibliography->getId());
            }
            foreach ($bookChapterBibliographies as $bibliography) {
                $document->addCacheDependency('book_chapter_bibliography.' . $bibliography->getId());
            }
            foreach ($onlineSourceBibliographies as $bibliography) {
                $document->addCacheDependency('online_source_bibliography.' . $bibliography->getId());
            }
            foreach ($bibliographies as $bibliography) {
                foreach ($bibliography->getCacheDependencies() as $cacheDependency) {
                    $document->addCacheDependency($cacheDependency);
                }
            }
            $document->setBibliographies($bibliographies);
        }
    }
}
