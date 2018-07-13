<?php

namespace AppBundle\ObjectStorage;

use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Model\Document;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Role;

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

    protected function setPersonRoles(array &$documents): void
    {
        $rawRoles = $this->dbs->getPersonRoles(self::getIds($documents));
        if (!empty($rawRoles)) {
            $personIds = self::getUniqueIds($rawRoles, 'person_id');

            $persons = [];
            if (count($personIds) > 0) {
                $persons = $this->container->get('person_manager')->getShortPersonsByIds($personIds);
            }

            // Direct roles
            foreach ($rawRoles as $raw) {
                $documents[$raw['manuscript_id']]
                    ->addPersonRole(
                        new Role($raw['role_id'], json_decode($raw['role_usage']), $raw['role_system_name'], $raw['role_name']),
                        $persons[$raw['person_id']]
                    )
                    ->addCacheDependency('role.' . $raw['role_id'])
                    ->addCacheDependency('person_short.' . $raw['person_id']);
                foreach ($persons[$raw['person_id']]->getCacheDependencies() as $cacheDependency) {
                    $documents[$raw['manuscript_id']]
                        ->addCacheDependency($cacheDependency);
                }
            }
        }
    }

    protected function updatePersonRole(Document $document, Role $role, array $persons): void
    {
        if (!is_array($persons)) {
            throw new BadRequestHttpException('Incorrect ' . $role->getSystemName() . ' data.');
        }
        foreach ($persons as $person) {
            if (!is_object($person)
                || (property_exists($person, 'id') && !is_numeric($person->id))
            ) {
                throw new BadRequestHttpException('Incorrect ' . $role->getSystemName() . ' data.');
            }
        }

        $personRoles = $document->getPersonRoles();
        $oldPersons = isset($personRoles[$role->getSystemName()]) ? $personRoles[$role->getSystemName()][1] : [];

        list($delIds, $addIds) = self::calcDiff($persons, $oldPersons);

        $this->dbs->beginTransaction();
        try {
            if (count($delIds) > 0) {
                $this->dbs->delPersonRole($document->getId(), $role->getId(), $delIds);
            }
            foreach ($addIds as $addId) {
                $this->dbs->addPersonRole($document->getId(), $role->getId(), $addId);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
    }
}
