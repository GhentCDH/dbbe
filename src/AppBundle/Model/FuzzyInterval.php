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
}
