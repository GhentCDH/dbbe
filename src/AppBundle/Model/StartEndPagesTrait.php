<?php

namespace AppBundle\Model;

trait StartEndPagesTrait
{
    private $startPage;
    private $endPage;

    public function setStartPage(string $startPage = null)
    {
        $this->startPage = $startPage;

        return $this;
    }

    public function getStartPage()
    {
        return $this->startPage;
    }

    public function setEndPage(string $endPage = null)
    {
        $this->endPage = $endPage;

        return $this;
    }

    public function getEndPage()
    {
        return $this->endPage;
    }

    public function formatStartEndPages(string $prefix = ''): string
    {
        if (empty($this->startPage)) {
            return '';
        }
        if (empty($this->endPage)) {
            return $prefix . $this->startPage;
        }
        return $prefix . $this->startPage . '-' . $this->endPage;
    }
}
