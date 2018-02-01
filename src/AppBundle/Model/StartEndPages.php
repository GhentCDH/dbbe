<?php

namespace AppBundle\Model;

trait StartEndPages
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
}
