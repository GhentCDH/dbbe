<?php

namespace AppBundle\ObjectStorage;

use DateTime;
use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\BlogPost;

/**
 * ObjectManager for blog posts
 * Servicename: blog_post_manager
 */
class BlogPostManager extends DocumentManager
{
    /**
     * Get blog posts with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $blogPosts = [];
        if (!empty($ids)) {
            $rawBlogPosts = $this->dbs->getMiniInfoByIds($ids);

            $blogIds = self::getUniqueIds($rawBlogPosts, 'blog_id');
            $blogs = $this->container->get('blog_manager')->getMini($blogIds);

            foreach ($rawBlogPosts as $rawBlogPost) {
                $blogPosts[$rawBlogPost['blog_post_id']] = new BlogPost(
                    $rawBlogPost['blog_post_id'],
                    $blogs[$rawBlogPost['blog_id']],
                    $rawBlogPost['url'],
                    $rawBlogPost['title'],
                    $rawBlogPost['post_date'] != null ? new DateTime($rawBlogPost['post_date']): null
                );
            }

            $this->setPersonRoles($blogPosts);
        }

        return $blogPosts;
    }

    /**
     * Get blog posts with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $blogPosts = $this->getMini($ids);

        $this->setComments($blogPosts);

        $this->setManagements($blogPosts);

        return $blogPosts;
    }

    /**
     * Get a single blog post with all information
     * @param  int        $id
     * @return BlogPost
     */
    public function getFull(int $id): BlogPost
    {
        // Get basic information
        $blogPosts = $this->getShort([$id]);
        if (count($blogPosts) == 0) {
            throw new NotFoundHttpException('Blog post with id ' . $id .' not found.');
        }

        $this->setCreatedAndModifiedDates($blogPosts);

        $this->setInverseIdentifications($blogPosts);

        $this->setInverseBibliographies($blogPosts);

        $this->setUrls($blogPosts);

        return $blogPosts[$id];
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
     * Get all blog posts that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getReferenceDependencies(array $referenceIds): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByReferenceIds($referenceIds), 'getMini');
    }

    /**
     * Add a new blog post
     * @param  stdClass $data
     * @return BlogPost
     */
    public function add(stdClass $data): BlogPost
    {
        if (!property_exists($data, 'author')
            || !is_array($data->author)
            || empty($data->author)
            || !property_exists($data, 'blog')
            || !is_object($data->blog)
            || !property_exists($data->blog, 'id')
            || !is_numeric($data->blog->id)
            || empty($data->blog->id)
            || !property_exists($data, 'url')
            || !is_string($data->url)
            || empty($data->url)
            || !property_exists($data, 'title')
            || !is_string($data->title)
            || empty($data->title)
            || (
                property_exists($data, 'postDate')
                && !is_string($data->postDate)
                && !empty($data->postDate)
            )
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new blog post');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->blog->id, $data->url, $data->title, property_exists($data, 'postDate') ? $data->postDate : null);

            unset($data->blog);
            unset($data->url);
            unset($data->name);
            unset($data->postDate);

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
     * Update new or existing blog post
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new blog post
     * @return BlogPost
     */
    public function update(int $id, stdClass $data, bool $isNew = false): BlogPost
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Blog post with id ' . $id .' not found.');
            }

            $changes = [
                'mini' => $isNew,
            ];
            if (property_exists($data, 'blog')) {
                // Blog is a required field
                if (!is_object($data->blog)
                    || !property_exists($data->blog, 'id')
                    || !is_numeric($data->blog->id)
                    || empty($data->blog->id)
                ) {
                    throw new BadRequestHttpException('Incorrect blog data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateBlog($id, $data->blog->id);
            }
            if (property_exists($data, 'url')) {
                // Url is a required field
                if (!is_string($data->url) || empty($data->url)) {
                    throw new BadRequestHttpException('Incorrect base url data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateUrl($id, $data->url);
            }
            if (property_exists($data, 'title')) {
                // Title is a required field
                if (!is_string($data->title) || empty($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            if (property_exists($data, 'postDate')) {
                // Post date is not a required field
                if (!is_string($data->postDate) && !empty($data->postDate)) {
                    throw new BadRequestHttpException('Incorrect postDate data.');
                }
                $changes['mini'] = true;
                $this->dbs->updatePostDate($id, $data->postDate);
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
