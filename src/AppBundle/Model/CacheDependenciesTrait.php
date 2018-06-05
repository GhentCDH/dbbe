<?php

namespace AppBundle\Model;

trait CacheDependenciesTrait
{
    protected $cacheDependencies;

    public function addCacheDependency(string $cacheDependency)
    {
        if (!isset($this->cacheDependencies)) {
            $this->cacheDependencies = [];
        }
        if (!in_array($cacheDependency, $this->cacheDependencies)) {
            $this->cacheDependencies[] = $cacheDependency;
        }

        return $this;
    }

    public function removeCacheDependency(string $cacheDependency)
    {
        $index = array_search($cacheDependency, $this->cacheDependencies);
        if ($index) {
            unset($this->cacheDependencies[$index]);
        }

        return $this;
    }

    public function getCacheDependencies(): array
    {
        if (!isset($this->cacheDependencies)) {
            return [];
        }
        return $this->cacheDependencies;
    }
}
