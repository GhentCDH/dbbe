<?php

namespace AppBundle\Model;

use DateTime;

/**
 */
class OnlineSource extends Entity
{
    /**
     * @var string
     */
    const CACHENAME = 'online_source';

    use CacheLinkTrait;
    use CacheObjectTrait;

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
        DateTime $lastAccessed
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
     * @return DateTime
     */
    public function getLastAccessed(): DateTime
    {
        return $this->lastAccessed;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->name
            . ' (last accessed: ' . $this->lastAccessed->format('Y-m-d') . ')'
            . '.';
    }

    /**
     * @return array
     */
    public function getJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'lastAccessed' => $this->lastAccessed->format('d/m/Y'),
        ];
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
            'id' => 3,
            'name' => 'Online Source',
        ];
        $result['title'] = $this->name;

        return $result;
    }

    /**
     * @param  array        $data
     * @return OnlineSource
     */
    public static function unlinkCache(array $data)
    {
        $onlineSource = new OnlineSource($data['id'], $data['url'], $data['name'], $data['lastAccessed']);

        foreach ($data as $key => $value) {
            $onlineSource->set($key, $value);
        }

        return $onlineSource;
    }
}
