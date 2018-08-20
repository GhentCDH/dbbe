<?php

namespace AppBundle\Model;

trait RawPagesTrait
{
    protected $rawPages;

    public function setRawPages(string $rawPages = null)
    {
        $this->rawPages = $rawPages;

        return $this;
    }

    public function getRawPages(): ?string
    {
        return $this->rawPages;
    }
}
