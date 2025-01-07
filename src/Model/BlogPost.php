<?php

namespace App\Model;

use DateTime;
use URLify;

use App\Utils\ArrayToJson;

/**
 */
class BlogPost extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'blog_post';

    use UrlsTrait;

    /**
     * @var Blog
     */
    protected $blog;
    /**
     * @var string
     */
    protected $url;
    /**
     * @var DateTime
     */
    protected $postDate;

    /**
     * @param int $id
     * @param Blog $blog
     * @param string $url
     * @param string $title
     * @param DateTime|null $postDate
     */
    public function __construct(
        int $id,
        Blog $blog,
        string $url,
        string $title,
        DateTime $postDate = null
    ) {
        $this->id = $id;
        $this->blog = $blog;
        $this->url = $url;
        $this->title = $title;
        $this->postDate = $postDate;

        // All blog posts are public
        $this->public = true;
    }

    /**
     * @return Blog
     */
    public function getBlog(): Blog
    {
        return $this->blog;
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
    public function getPostDate(): DateTime
    {
        return $this->postDate;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        $authorNames = [];
        if (isset($this->personRoles['author'])) {
            foreach ($this->personRoles['author'][1] as $author) {
                $authorNames[] = $author->getShortDescription();
            }
        }
        return
            implode(', ', $authorNames)
            . (!empty($authorNames) ? ', ' : '')
            . $this->title
            . (!empty($this->postDate) ? ' (posted on: ' . $this->postDate->format('Y-m-d') . ')' : '')
            . '.';
    }

    /**
     * Generate a sortKey; see Entity -> getBibliographiesForDisplay()
     *
     * @return string
     */
    public function getSortKey(): string
    {
        $sortKey = 'a';

        if (!empty($this->personRoles['author'])) {
            $lastName = reset($this->personRoles['author'][1])->getLastName();
            if (!empty($lastName)) {
                $sortKey .= URLify::filter($lastName);
            } else {
                $sortKey .= 'zzz';
            }
        } else {
            $sortKey .= 'zzz';
        }

        $sortKey .= 'title';

        return $sortKey;
    }

    /**
     * @return array
     */
    public function getJson(): array
    {
        $result = parent::getJson();

        $result['blog'] = $this->blog->getShortJson();
        $result['url'] = $this->url;
        $result['title'] = $this->title;

        if (!empty($this->postDate)) {
            $result['postDate'] = $this->postDate->format('d/m/Y');
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
            'id' => 8,
            'name' => 'Blog post',
            'id_name' => 8 . '_' . 'Blog post',
        ];
        $result['title'] = $this->title;
        $personRoles = $this->getPersonRoles();
        foreach ($personRoles as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($personRoles['author']) && count($personRoles['author'][1]) > 0) {
            $result['author_last_name'] = reset($personRoles['author'][1])->getLastName();
        }
        $publicPersonRoles = $this->getPublicPersonRoles();
        foreach ($publicPersonRoles as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($publicPersonRoles['author']) && count($publicPersonRoles['author'][1]) > 0) {
            $result['author_last_name_public'] = reset($publicPersonRoles['author'][1])->getLastName();
        }

        return $result;
    }
}
