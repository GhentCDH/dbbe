<?php

namespace App\Model;

use DateTime;

/**
 */
class OnlineSource extends Entity
{
    /**
     * @var string
     */
    const CACHENAME = 'online_source';

    use UrlsTrait;

    /**
     * @var string
     */
    protected $url;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var DateTime
     */
    protected $lastAccessed;

    /**
     * @param int      $id
     * @param string   $url
     * @param string   $name
     * @param DateTime $lastAccessed
     */
    public function __construct(
        int $id,
        string $url,
        string $name,
        DateTime $lastAccessed = null
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->name = $name;
        $this->lastAccessed = $lastAccessed;

        // All online sources are public
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return ?DateTime
     */
    public function getLastAccessed(): ?DateTime
    {
        return $this->lastAccessed;
    }

    public function getFormattedLastAccessed(): ?string
    {
        if ($this->getName() === 'DBBE') {
            return (new \DateTime())->format('Y-m-d');
        }

        return $this->lastAccessed->format('Y-m-d');
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->name
            . (!empty($this->lastAccessed)
                ? ' (last accessed: ' . $this->lastAccessed->format('Y-m-d') . ')'
                : '')
            . '.';
    }

    /**
     * Generate a sortKey; see Entity -> getBibliographiesForDisplay()
     *
     * @return string
     */
    public function getSortKey(): string
    {
        return 'z' . $this->name;
    }

    /**
     * @return array
     */
    public function getJson(): array
    {
        $result = parent::getJson();

        $result['name'] = $this->name;
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
            'url' =>$this->url,
        ];
    }

    public function getTitle(): ?string
    {
        return $this->name;
    }

    public function getTitleSortKey(): ?string
    {
        return $this->name;
    }
    /**
     * @return array
     */
    public function getElastic(): array
    {
        $result = parent::getElastic();
        $result['title_sort_key'] = $this->getTitleSortKey();

        $result['type'] = [
            'id' => 3,
            'name' => 'Online Source',
            'id_name' => 3 . '_' . 'Online Source',
        ];
        $result['title'] = $this->name;

        return $result;
    }


}
