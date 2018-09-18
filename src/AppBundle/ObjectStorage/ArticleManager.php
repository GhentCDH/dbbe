<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
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
        return $this->wrapLevelCache(
            Article::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $articles = [];
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

                return $articles;
            }
        );
    }

    /**
     * Get articles with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->getMini($ids);
    }

    /**
     * Get a single article with all information
     * @param  int        $id
     * @return Article
     */
    public function getFull(int $id): Article
    {
        return $this->wrapSingleLevelCache(
            Article::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic information
                $articles = $this->getShort([$id]);
                if (count($articles) == 0) {
                    throw new NotFoundHttpException('Article with id ' . $id .' not found.');
                }

                $this->setInverseBibliographies($articles);

                return $articles[$id];
            }
        );
    }

    /**
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMini(string $sortFunction = null): array
    {
        return parent::getAllMini($sortFunction == null ? 'getDescription' : $sortFunction);
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
     * Get all articles that are dependent on a specific person
     * @param  int   $personId
     * @param  bool  $short    Whether to return a short or mini person (default: false => mini)
     * @return array
     */
    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short ? 'getShort' : 'getMini');
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

        $cacheReload = [
            'mini' => $isNew,
        ];
        $roles = $this->container->get('role_manager')->getRolesByType('article');
        foreach ($roles as $role) {
            if (property_exists($data, $role->getSystemName())) {
                $cacheReload['mini'] = true;
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
                $cacheReload['mini'] = true;
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
                $cacheReload['mini'] = true;
                $this->dbs->updateJournal($id, $data->journal->id);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            if (!$isNew) {
                $this->clearCache($id, $cacheReload);
            }
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            // (re-)index in elastic search
            $this->ess->add($new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($new)) {
                $this->reset([$id]);
            }
            throw $e;
        }

        return $new;
    }

    /**
     * Delete an article
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws a not found exception when not found
            $old = $this->getFull($id);

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            // empty cache and remove from elasticsearch
            $this->reset([$id]);

            // commit transaction
            $this->dbs->commit();
        } catch (DependencyException $e) {
            $this->dbs->rollBack();
            throw new BadRequestHttpException($e->getMessage());
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return;
    }
}
