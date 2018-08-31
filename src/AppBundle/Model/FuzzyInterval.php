<?php

namespace AppBundle\Model;

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

    public function __toString()
    {
        // start and end are the same
        if ($this->start == $this->end) {
            return $this->start->__toString();
        }

        // different start and end
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

    public static function fromString(string $inputString = null): FuzzyInterval
    {
        $start = null;
        $end = null;
        if ($inputString != null && $inputString != '') {
            $regMatch = [];
            preg_match(
                '/^[(]([^,]*)[,]([^,]*)[,]([^,]*)[,]([^,]*)[)]$/',
                $inputString,
                $regMatch
            );
            if (count($regMatch) != 0) {
                $start = new FuzzyDate('(' . $regMatch[1] . ',' . $regMatch[2] . ')');
                $end = new FuzzyDate('(' . $regMatch[3] . ',' . $regMatch[4] . ')');
            }
        }

        return new FuzzyInterval($start ? $start : new FuzzyDate(), $end ? $end : new FuzzyDate());
    }
}
