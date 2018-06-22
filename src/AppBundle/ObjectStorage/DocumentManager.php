<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\FuzzyDate;

class DocumentManager extends EntityManager
{
    protected function setDates(array &$documents): void
    {
        $rawCompletionDates = $this->dbs->getCompletionDates(self::getIds($documents));
        foreach ($rawCompletionDates as $rawCompletionDate) {
            $documents[$rawCompletionDate['document_id']]
                ->setDate(new FuzzyDate($rawCompletionDate['completion_date']));
        }
    }

    protected function setPrevIds(array &$documents): void
    {
        $rawPrevIds = $this->dbs->getPrevIds(self::getIds($documents));
        foreach ($rawPrevIds as $rawPrevId) {
            $documents[$rawPrevId['document_id']]
                ->setPrevId($rawPrevId['prev_id']);
        }
    }

    protected function setBibliographies(array &$documents): void
    {
        $rawBibliographies = $this->dbs->getBibliographies(self::getIds($documents));
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
