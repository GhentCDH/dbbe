<?php

namespace AppBundle\Model;

use DateTime;
use NumberFormatter;
use stdClass;

class FuzzyDate
{
    /**
     * The earliest possible date of the fuzzydate, null if unknown
     * @var DateTime|null
     */
    private $floor;
    /**
     * The latest possible date of the fuzzydate, null if unknown
     * @var DateTime|null
     */
    private $ceiling;

    public function __construct(string $inputString = null)
    {
        $this->floor = null;
        $this->ceiling = null;
        if ($inputString != null && $inputString != '') {
            $regMatch = [];
            preg_match(
                '/^[(]([^,]*)[,]([^,]*)[)]$/',
                $inputString,
                $regMatch
            );
            if (count($regMatch) != 0) {
                $floorString = $regMatch[1];
                $ceilingString = $regMatch[2];

                $datePattern = '/^["]?(\d{4}[-]\d{2}-\d{2})([ ]BC)?["]?$/';

                $dateMatch = [];
                preg_match($datePattern, $floorString, $dateMatch);
                if (count($dateMatch) == 2) {
                    $this->floor = new DateTime($floorString);
                } elseif (count($dateMatch) == 3) {
                    $this->floor = new DateTime('-' . substr($floorString, 1, 10));
                }

                $dateMatch = [];
                preg_match($datePattern, $ceilingString, $dateMatch);
                if (count($dateMatch) == 2) {
                    $this->ceiling = new DateTime($ceilingString);
                } elseif (count($dateMatch) == 3) {
                    $this->ceiling = new DateTime('-' . substr($ceilingString, 1, 10));
                }
            }
        }

        return $this;
    }

    public function __toString()
    {
        // unknown floor and ceiling
        if (empty($this->floor) && empty($this->ceiling)) {
            return '';
        }

        $floorYear = '';
        if (!empty($this->floor)) {
            $floorYear = preg_replace('/^([-])?0*(\d+)/', '$1$2', $this->floor->format('Y'));
            if (substr($floorYear, 0, 1 ) === '-') {
                $floorYear = substr($floorYear, 1) . ' BC';
            }
        }
        $ceilingYear = '';
        if (!empty($this->ceiling)) {
            $ceilingYear = preg_replace('/^([-])?0*(\d+)/', '$1$2', $this->ceiling->format('Y'));
            if (substr($ceilingYear, 0, 1) === '-') {
                $ceilingYear = substr($ceilingYear, 1) . ' BC';
            }
        }

        // unknown floor
        if (empty($this->floor)) {
            // year
            if ($this->ceiling->format('m-d') == '12-31') {
                return 'before ' . $ceilingYear;
            }
            return 'before ' . $this->ceiling->format('d/m/') . $ceilingYear;
        }

        // unknown ceiling
        if (empty($this->ceiling)) {
            // year
            if ($this->floor->format('m-d') == '01-01') {
                return 'after ' . $floorYear;
            }
            return 'after ' . $this->floor->format('d/m/') . $floorYear;
        }

        // exact century or centuries
        if ($this->floor->format('y-m-d') == '01-01-01'
            && $this->ceiling->format('y-m-d') == '00-12-31'
        ) {
            $floorCentury = (int)($this->floor->format('Y') / 100);
            $ceilingCentury = (int)($this->ceiling->format('Y') / 100);

            $nf = new NumberFormatter('en_US', NumberFormatter::ORDINAL);
            $ceilingCenturyF = $nf->format($ceilingCentury);

            if ($floorCentury == $ceilingCentury - 1) {
                return $ceilingCenturyF . ' c.';
            } else {
                $floorCenturyF = $nf->format($floorCentury + 1);
                return $floorCenturyF . '-' . $ceilingCenturyF . ' c.';
            }
        }

        // exact year or years
        if ($this->floor->format('m-d') == '01-01'
            && $this->ceiling->format('m-d') == '12-31'
        ) {
            if ($floorYear == $ceilingYear) {
                return $floorYear;
            } else {
                return $floorYear . '-' . $ceilingYear;
            }
        }

        // exact date
        if ($this->floor == $this->ceiling) {
            return $this->floor->format('d/m/') . $floorYear;
        }

        // default: return all information
        return $this->floor->format('d/m/') . $floorYear . '-' . $this->ceiling->format('d/m/') . $ceilingYear;
    }

    /**
     * Check if any property is set
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->floor) && empty($this->ceiling);
    }

    /**
     * Get the value of Floor
     *
     * @return DateTime|null
     */
    public function getFloor(): ?DateTime
    {
        return $this->floor;
    }

    /**
     * Set the value of Floor
     *
     * @param DateTime|null $floor
     *
     * @return self
     */
    public function setFloor(DateTime $floor = null): FuzzyDate
    {
        $this->floor = $floor;

        return $this;
    }

    /**
     * Get the value of Ceiling
     *
     * @return DateTime|null
     */
    public function getCeiling(): ?DateTime
    {
        return $this->ceiling;
    }

    /**
     * Set the value of Ceiling
     *
     * @param DateTime|null $ceiling
     *
     * @return self
     */
    public function setCeiling(DateTime $ceiling = null): FuzzyDate
    {
        $this->ceiling = $ceiling;

        return $this;
    }

    public function getSortKey(): string
    {
        return $this->floor->format('Ymd') . $this->ceiling->format('Ymd');
    }

    public function getJson(): array
    {
        return [
            'floor' => empty($this->floor) ? null : $this->floor->format('Y-m-d'),
            'ceiling' => empty($this->ceiling) ? null : $this->ceiling->format('Y-m-d'),
        ];
    }

    public static function fromDB(stdClass $input): FuzzyDate
    {
        return (new FuzzyDate())
            ->setFloor(new DateTime($input->floor))
            ->setCeiling(new DateTime($input->ceiling));
    }
}
