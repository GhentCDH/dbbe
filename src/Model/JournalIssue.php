<?php

namespace App\Model;

class JournalIssue extends Document
{
    const CACHENAME = 'journal_issue';

    protected $journal;
    protected $year;
    protected $forthcoming;
    protected $series;
    protected $volume;
    protected $number;

    public function __construct(
        int $id,
        Journal $journal,
        string $year = null,
        bool $forthcoming,
        string $series = null,
        string $volume = null,
        string $number = null
    ) {
        $this->id = $id;
        $this->journal = $journal;
        $this->year = $year;
        $this->forthcoming = $forthcoming;
        $this->series = $series;
        $this->volume = $volume;
        $this->number = $number;

        $this->public = true;
    }

    public function getJournal(): Journal
    {
        return $this->journal;
    }

    public function getYear(): ?string
    {
        return $this->year;
    }

    public function getForthcoming(): bool
    {
        return $this->forthcoming;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getDescription(): string
    {
        return (
            $this->forthcoming
                ? '(forthcoming)'
                : $this->year
        )
        . ', ' . $this->journal->getTitle()
            . (
            !empty($this->series)
                ? ' (Series ' . $this->series . ')'
                : ''
            )
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
            'journalId' => $this->journal->getId(),
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        $result['name'] = $this->getDescription();
        $result['journal'] = $this->journal->getShortJson();

        if (!empty($this->year)) {
            $result['year'] = $this->year;
        }
        $result['forthcoming'] = $this->forthcoming;
        if (!empty($this->series)) {
            $result['series'] = $this->series;
        }
        if (!empty($this->volume)) {
            $result['volume'] = $this->volume;
        }
        if (!empty($this->number)) {
            $result['number'] = $this->number;
        }

        return $result;
    }
}
