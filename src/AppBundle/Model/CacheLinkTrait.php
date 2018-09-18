<?php

namespace AppBundle\Model;

trait CacheLinkTrait
{
    protected $cacheLevel;

    /**
     * @param string $cacheLevel
     */
    public function setCacheLevel(string $cacheLevel)
    {
        $this->cacheLevel = $cacheLevel;

        return $this;
    }

    public function getCacheLink()
    {
        $cacheLink = 'C:' . static::CACHENAME . ':';
        if (isset($this->cacheLevel)) {
            $cacheLink .= $this->cacheLevel . ':';
        }
        $cacheLink .= $this->getId();
        return $cacheLink;
    }
}
