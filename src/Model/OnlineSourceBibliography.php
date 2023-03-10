<?php

namespace App\Model;

class OnlineSourceBibliography extends Bibliography
{
    protected $onlineSource;
    protected $relUrl;

    public function __construct(int $id)
    {
        parent::__construct($id, 'onlineSource');
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

    public function getRelUrl(): ?string
    {
        return $this->relUrl;
    }

    public function getDescription(): string
    {
        return $this->onlineSource->getDescription();
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'onlineSource' => $this->onlineSource->getShortJson(),
            'relUrl' => $this->relUrl,
        ];

        if (isset($this->referenceType)) {
            $result['referenceType'] = $this->referenceType->getShortJson();
        }
        if (isset($this->image)) {
            $result['image'] = $this->image;
        }

        return $result;
    }
}
