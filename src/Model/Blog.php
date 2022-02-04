<?php

namespace App\Model;

use DateTime;

/**
 */
class Blog extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'blog';

    use UrlsTrait;

    /**
     * @var string
     */
    protected $url;
    /**
     * @var DateTime
     */
    protected $lastAccessed;
    /**
     * @var array
     */
    protected $posts = [];

    /**
     * @param int      $id
     * @param string   $url
     * @param string   $title
     * @param DateTime|null $lastAccessed
     */
    public function __construct(
        int $id,
        string $url,
        string $title,
        DateTime $lastAccessed = null
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->title = $title;
        $this->lastAccessed = $lastAccessed;

        // All blogs are public
        $this->public = true;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return DateTime
     */
    public function getLastAccessed(): ?DateTime
    {
        return $this->lastAccessed;
    }

    public function addPost(BlogPost $blogPost): Blog
    {
        $this->posts[$blogPost->getId()] = $blogPost;

        return $this;
    }

    public function getPosts(): array
    {
        return $this->posts;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->title
            . (!empty($this->lastAccessed) ? ' (last accessed: ' . $this->lastAccessed->format('Y-m-d') . ')' : '')
            . '.';
    }

    /**
     * Generate a sortKey; see Entity -> getBibliographiesForDisplay()
     *
     * @return string
     */
    public function getSortKey(): string
    {
        return 'z' . $this->title;
    }

    /**
     * @return array
     */
    public function getJson(): array
    {
        $result = parent::getJson();

        $result['title'] = $this->title;
        $result['url'] = $this->url;

        if (!empty($this->lastAccessed)) {
            $result['lastAccessed'] = $this->lastAccessed->format('d/m/Y');
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    /**
     * @return array
     */
    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['type'] = [
            'id' => 7,
            'name' => 'Blog',
        ];
        $result['title'] = $this->title;

        return $result;
    }
}
