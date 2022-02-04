<?php

namespace App\Model;

trait StartEndPagesTrait
{
    protected $startPage;
    protected $endPage;

    public function setStartPage(string $startPage = null)
    {
        $this->startPage = $startPage;

        return $this;
    }

    public function getStartPage(): ?string
    {
        return $this->startPage;
    }

    public function setEndPage(string $endPage = null)
    {
        $this->endPage = $endPage;

        return $this;
    }

    public function getEndPage(): ?string
    {
        return $this->endPage;
    }

    public function formatStartEndPages(string $prefix = '', string $raw = null): string
    {
        if (empty($this->startPage)) {
            if (!empty($raw)) {
                return $prefix . $raw;
            } else {
                return '';
            }
        }
        if (empty($this->endPage) || $this->startPage === $this->endPage) {
            return $prefix . $this->startPage;
        }
        return $prefix . $this->startPage . '-' . $this->endPage;
    }
}
