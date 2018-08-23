<?php

namespace AppBundle\Model;

class Journal extends Document
{
    const CACHENAME = 'journal';

    use CacheLinkTrait;

    protected $title;
    protected $year;
    protected $volume;
    protected $number;

    public function __construct(
        int $id,
        string $title,
        int $year,
        int $volume = null,
        int $number = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->year = $year;
        $this->volume = $volume;
        $this->number = $number;

        $this->public = true;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function getDescription(): string
    {
        return $this->year
        . ', ' . $this->title
        . (
            !empty($this->volume)
                ? ', ' . $this->volume
                : ''
        )
        . (
            !empty($this->number)
                ? '(' . $this->number . ')'
                : ''
        );
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        $result['name'] = $this->getDescription();

        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->year)) {
            $result['year'] = $this->year;
        }
        if (!empty($this->volume)) {
            $result['volume'] = $this->volume;
        }
        if (!empty($this->number)) {
            $result['number'] = $this->number;
        }

        return $result;
    }

    public static function unlinkCache($data)
    {
        $journal = new Journal($data['id'], $data['title'], $data['year']);

        foreach ($data as $key => $value) {
            $journal->set($key, $value);
        }

        return $journal;
    }
}
