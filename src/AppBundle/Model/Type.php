<?php

namespace AppBundle\Model;

class Type extends Document
{
    const CACHENAME = 'type';

    use CacheLinkTrait;

    protected $incipit;
    protected $numberOfVerses;

    public function setIncipit(string $incipit): Type
    {
        $this->incipit = $incipit;

        return $this;
    }

    public function getIncipit(): string
    {
        return $this->incipit;
    }

    public function setNumberOfVerses(int $numberOfVerses = null): Type
    {
        $this->numberOfVerses = $numberOfVerses;

        return $this;
    }

    public function getNumberOfVerses(): ?int
    {
        return $this->numberOfVerses;
    }

    public function getDescription(): string
    {
        return $this->incipit;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->incipit,
        ];
    }
}
