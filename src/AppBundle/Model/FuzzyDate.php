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
}
