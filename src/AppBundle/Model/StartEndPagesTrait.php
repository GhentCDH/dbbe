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
        if (empty($this->page_start)) {
            return '';
        }
        if (empty($this->page_end)) {
            return $prefix . $this->page_start;
        }
        return $prefix . $this->page_start . '-' . $this->page_end;
    }
}
