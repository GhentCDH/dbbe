<?php

namespace App\ObjectStorage;

use DateTime;
use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Model\Blog;

/**
 * ObjectManager for blogs
 */
class BlogManager extends DocumentManager
{
    /**
     * Get blogs with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $blogs = [];
        if (!empty($ids)) {
            $rawBlogs = $this->dbs->getMiniInfoByIds($ids);

            foreach ($rawBlogs as $rawBlog) {
                $blogs[$rawBlog['blog_id']] = new Blog(
                    $rawBlog['blog_id'],
                    $rawBlog['url'],
                    $rawBlog['title'],
                    $rawBlog['last_accessed'] != null ? new DateTime($rawBlog['last_accessed']): null
                );
            }
        }

        return $blogs;
    }

    /**
     * Get blogs with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $blogs = $this->getMini($ids);

        $this->setComments($blogs);

        $this->setManagements($blogs);

        return $blogs;
    }

    /**
     * Get a single blog with all information
     * @param  int        $id
     * @return Blog
     */
    public function getFull(int $id): Blog
    {
        // Get basic information
        $blogs = $this->getShort([$id]);
        if (count($blogs) == 0) {
            throw new NotFoundHttpException('Blog with id ' . $id .' not found.');
        }

        $this->setCreatedAndModifiedDates($blogs);

        $this->setInverseIdentifications($blogs);

        $this->setInverseBibliographies($blogs);

        $this->setUrls($blogs);

        $blog = $blogs[$id];

        // Posts
        $rawPosts = $this->dbs->getPosts([$id]);
        $blogPostIds = self::getUniqueIds($rawPosts, 'blog_post_id');
        $blogPosts = $this->container->get(BlogPostManager::class)->getMini($blogPostIds);
        foreach ($rawPosts as $rawPost) {
            $blog->addPost($blogPosts[$rawPost['blog_post_id']]);
        }

        $blog->sortPosts();

        return $blog;
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
     * Add a new blog
     * @param  stdClass $data
     * @return Blog
     */
    public function add(stdClass $data): Blog
    {
        if (!property_exists($data, 'url')
            || !is_string($data->url)
            || empty($data->url)
            || !property_exists($data, 'title')
            || !is_string($data->title)
            || empty($data->title)
            || (
                property_exists($data, 'lastAccessed')
                && !is_string($data->lastAccessed)
                && !empty($data->lastAccessed)
            )
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new blog');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->url, $data->title, property_exists($data, 'lastAccessed') ? $data->lastAccessed : null);

            unset($data->url);
            unset($data->title);
            unset($data->lastAccessed);

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
     * Update new or existing blog
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new blog
     * @return Blog
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Blog
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Blog with id ' . $id .' not found.');
            }

            $changes = [
                'mini' => $isNew,
            ];
            if (property_exists($data, 'url')) {
                // Url is a required field
                if (!is_string($data->url) || empty($data->url)) {
                    throw new BadRequestHttpException('Incorrect base url data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateUrl($id, $data->url);
            }
            if (property_exists($data, 'title')) {
                // title is a required field
                if (!is_string($data->title) || empty($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            if (property_exists($data, 'lastAccessed')) {
                // Last accessed is not a required field
                if (!is_string($data->lastAccessed) && !empty($data->lastAccessed)) {
                    throw new BadRequestHttpException('Incorrect lastAccessed data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateLastAccessed($id, $data->lastAccessed);
            }
            $this->updateUrlswrapper($old, $data, $changes, 'full');
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePublicComment($id, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }
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
            if ($isNew) {
                $this->updateElasticByIds([$id]);
            } elseif (isset($new) && isset($old)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }
}
