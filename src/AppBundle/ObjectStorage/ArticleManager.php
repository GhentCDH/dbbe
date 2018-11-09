<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Article;

/**
 * ObjectManager for articles
 * Servicename: article_manager
 */
class ArticleManager extends DocumentManager
{
    /**
     * Get articles with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $articles = [];
        if (!empty($ids)) {
            $rawArticles = $this->dbs->getMiniInfoByIds($ids);

            $journalIds = self::getUniqueIds($rawArticles, 'journal_id');
            $journals = $this->container->get('journal_manager')->get($journalIds);

            foreach ($rawArticles as $rawArticle) {
                $article = (new Article(
                    $rawArticle['article_id'],
                    $rawArticle['article_title'],
                    $journals[$rawArticle['journal_id']]
                ))
                    ->setStartPage($rawArticle['article_page_start'])
                    ->setEndPage($rawArticle['article_page_end'])
                    ->setRawPages($rawArticle['article_raw_pages']);

                $articles[$rawArticle['article_id']] = $article;
            }

            $this->setPersonRoles($articles);
        }

        return $articles;
    }

    /**
     * Get articles with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $articles = $this->getMini($ids);

        $this->setIdentifications($articles);

        $this->setComments($articles);

        $this->setManagements($articles);

        return $articles;
    }

    /**
     * Get a single article with all information
     * @param  int        $id
     * @return Article
     */
    public function getFull(int $id): Article
    {
        // Get basic information
        $articles = $this->getShort([$id]);

        if (count($articles) == 0) {
            throw new NotFoundHttpException('Article with id ' . $id .' not found.');
        }

        $this->setInverseBibliographies($articles);

        return $articles[$id];
    }

    /**
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return parent::getAllMiniShortJson($sortFunction == null ? 'getDescription' : $sortFunction);
    }

    /**
     * Get all articles that are dependent on a specific journal
     * @param  int   $journalId
     * @return array
     */
    public function getJournalDependencies(int $journalId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByJournalId($journalId), 'getMini');
    }

    /**
     * Get all articles that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getReferenceDependencies(array $referenceIds): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByReferenceIds($referenceIds), 'getMini');
    }

    /**
     * Add a new article
     * @param  stdClass $data
     * @return Article
     */
    public function add(stdClass $data): Article
    {
        if (!property_exists($data, 'title')
            || !is_string($data->title)
            || empty($data->title)
            || !property_exists($data, 'journal')
            || !is_object($data->journal)
            || !property_exists($data->journal, 'id')
            || !is_numeric($data->journal->id)
            || empty($data->journal->id)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new article');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->title, $data->journal->id);

            unset($data->title);
            unset($data->journal);

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update new or existing article
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new article
     * @return Article
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Article
    {
        $old = $this->getFull($id);
        if ($old == null) {
            throw new NotFoundHttpException('Article with id ' . $id .' not found.');
        }

        $changes = [
            'mini' => $isNew,
        ];
        $roles = $this->container->get('role_manager')->getByType('article');
        foreach ($roles as $role) {
            if (property_exists($data, $role->getSystemName())) {
                $changes['mini'] = true;
                $this->updatePersonRoleWithRank($old, $role, $data->{$role->getSystemName()});
            }
        }

        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'title')) {
                // Title is a required field
                if (!is_string($data->title) || empty($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            if (property_exists($data, 'journal')) {
                // Journal is a required field
                if (!is_object($data->journal)
                    || !property_exists($data->journal, 'id')
                    || !is_numeric($data->journal->id)
                    || empty($data->journal->id)
                ) {
                    throw new BadRequestHttpException('Incorrect journal data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateJournal($id, $data->journal->id);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'article');
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // (re-)index in elastic search
            $this->ess->add($new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if (!$isNew && isset($new)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }
}
