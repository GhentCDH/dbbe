<?php

namespace AppBundle\Model;

trait CacheLinkTrait
{
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
