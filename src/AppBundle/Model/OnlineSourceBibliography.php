<?php

namespace AppBundle\Model;

class OnlineSourceBibliography extends Bibliography
{
    private $onlineSource;
    private $relUrl;

    public function __construct(int $id)
    {
        parent::__construct($id, 'online_source');
    }

    public function setOnlineSource(OnlineSource $onlineSource): OnlineSourceBibliography
    {
        $this->onlineSource = $onlineSource;

        return $this;
    }

    public function getOnlineSource(): OnlineSource
    {
        return $this->onlineSource;
    }

    public function setRelUrl(string $relUrl = null): OnlineSourceBibliography
    {
        $this->relUrl = $relUrl;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->onlineSource->getBaseUrl()
            . $this->relUrl;
    }

    public function getDescription(): string
    {
        return $this->onlineSource->getName()
            . ' (last accessed: ' . $this->onlineSource->getLastAccessed()->format('Y-m-d') . ')'
            . '.';
    }

    public function getShortJson(): array
    {
        throw new \Exception('Not implemented');
    }
}
