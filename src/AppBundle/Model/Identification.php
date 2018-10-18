<?php

namespace AppBundle\Model;

class Identification
{
    protected $identifier;
    protected $identifications;
    protected $extra;

    public static function constructFromDB(
        Identifier $identifier,
        array $identifications,
        array $authorityIds,
        string $extra = null,
        array $identificationIds
    ) {
        $identification = new Identification();

        $identification->identifier = $identifier;
        $identification->identifications = [];
        if (count($identificationIds) > 1) {
            foreach ($identifications as $key => $value) {
                $identification->identifications[] =
                    self::numberToRoman(array_search($authorityIds[$key], $identificationIds) + 1) . '.' . $value;
            }
        } else {
            $identification->identifications = explode(', ', $identifications[0]);
        }
        if ($extra != null && $extra != '') {
            $identification->extra = $extra;
        }

        return $identification;
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public function getIdentifications(): array
    {
        return $this->identifications;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    /**
     * @param int $number
     * @return string
     */
    public static function numberToRoman($number)
    {
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if ($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }
}
