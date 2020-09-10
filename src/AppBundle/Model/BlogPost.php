<?php

namespace AppBundle\Model;

use DateTime;

use AppBundle\Utils\ArrayToJson;

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
     * @var string
     */
    protected $url;
    /**
     * @var DateTime
     */
    protected $postDate;

    /**
     * @param int      $id
     * @param string   $url
     * @param string   $title
     * @param DateTime $postDate
     */
    public function __construct(
        int $id,
        string $url,
        string $title,
        DateTime $postDate
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->title = $title;
        $this->postDate = $postDate;

        // All blog posts are public
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
    public function getPostDate(): ?DateTime
    {
        return $this->postDate;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->title. ' (posted on: ' . $this->postDate->format('Y-m-d') . ').';
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
        $result['postDate'] = $this->postDate;

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
            'url' =>$this->url,
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
