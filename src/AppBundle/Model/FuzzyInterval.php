<?php

namespace AppBundle\Model;

use stdClass;

class FuzzyInterval
{
    private $start;
    private $end;

    public function __construct(FuzzyDate $start, FuzzyDate $end)
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

    public function getStart(): FuzzyDate
    {
        return $this->start;
    }

    public function getEnd(): FuzzyDate
    {
        return $this->end;
    }

    public function __toString()
    {
        return $this->start->__toString() . ' - ' . $this->end->__toString();
    }

    /**
     * Check if any property is set
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->start->isEmpty() && $this->end->isEmpty();
    }

    public function getSortKey(): string
    {
        return $this->start->getFloor()->format('Ymd') . $this->start->getCeiling()->format('Ymd') . $this->end->getFloor()->format('Ymd') . $this->end->getCeiling()->format('Ymd');
    }

    public function getJson(): array
    {
        return [
            'start' => $this->start->getJson(),
            'end' => $this->end->getJson(),
        ];
    }

    public static function fromDB(stdClass $input): FuzzyInterval
    {
        return new FuzzyInterval(
            new FuzzyDate('(' . $input->start_floor . ',' . $input->start_ceiling . ')'),
            new FuzzyDate('(' . $input->end_floor . ',' . $input->end_ceiling . ')')
        );
    }
}
