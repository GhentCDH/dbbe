<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Document;
use AppBundle\Model\FuzzyDate;

class DocumentManager extends ObjectManager
{
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

    protected function setBibliographies(Document &$document): void
    {
        $rawBibliographies = $this->dbs->getBibliographies([$document->getId()]);
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

    protected function setComments(Document &$document): void
    {
        // Comments
        $rawComments = $this->dbs->getComments([$document->getId()]);
        if (count($rawComments) == 1) {
            $document->setPublicComment($rawComments[0]['public_comment']);
            $document->setPrivateComment($rawComments[0]['private_comment']);
        }
    }
}
