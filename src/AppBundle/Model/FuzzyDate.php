<?php

namespace AppBundle\Model;

class FuzzyDate
{
    private $floor;
    private $ceiling;

    public function __construct(string $inputString = null)
    {
        if ($inputString != null && $inputString != '') {
            $regMatch = [];
            preg_match(
                '/[(](\d{4}[-]\d{2}-\d{2})[,](\d{4}[-]\d{2}-\d{2})[)]/',
                $inputString,
                $regMatch
            );
            if (count($regMatch) != 0) {
                $this->floor = $regMatch[1];
                $this->ceiling = $regMatch[2];
                return $this;
            }
        }
        $this->floor = null;
        $this->ceiling = null;

        return $this;
    }

    /**
     * Get the value of Floor
     *
     * @return mixed
     */
    public function getFloor()
    {
        return $this->floor;
    }

    /**
     * Get the year of the floor date
     *
     * @return mixed
     */
    public function getFloorYear()
    {
        return $this->getYear($this->floor);
    }

    /**
     * Set the value of Floor
     *
     * @param string $floor
     *
     * @return self
     */
    public function setFloor(string $floor)
    {
        $this->floor = $floor;

        return $this;
    }

    /**
     * Get the value of Ceiling
     *
     * @return mixed
     */
    public function getCeiling()
    {
        return $this->ceiling;
    }

    /**
     * Get the year of the ceiling date
     *
     * @return mixed
     */
    public function getCeilingYear()
    {
        return $this->getYear($this->ceiling);
    }

    /**
     * Set the value of Ceiling
     *
     * @param string $ceiling
     *
     * @return self
     */
    public function setCeiling(string $ceiling)
    {
        $this->ceiling = $ceiling;

        return $this;
    }

    /**
     * Extracts the year from a datestring and removes leading zeros.
     * @param  string $dateString String in the YYYY-MM-DD format
     * @return mixed              Integer representing the year.
     */
    private function getYear(string $dateString)
    {
        return (int)(explode('-', $dateString)[0]);
    }
}
