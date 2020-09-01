<?php

namespace AppBundle\Model;

trait UrlsTrait
{
    /**
     * @var array
     */
    protected $urls;

    public function setURLS(array $urls)
    {
        $this->urls = $urls;

        return $this;
    }

    public function addUrl(Url $url)
    {
        $this->urls[] = $url;

        return $this;
    }

    public function getUrls()
    {
        return $this->urls;
    }
}