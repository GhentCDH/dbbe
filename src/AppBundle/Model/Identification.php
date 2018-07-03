<?php

namespace AppBundle\Model;

class Identification
{
    private $identifier;
    private $identifications;

    public function __construct(Identifier $identifier, array $identifiers, array $authorityIds, array $identifierIds)
    {
        $this->identifier = $identifier;
        $this->identifications = [];
        if (count($identifierIds) > 1) {
            foreach ($identifiers as $key => $value) {
                $this->identifications[] = self::numberToRoman(array_search($authorityIds[$key], $identifierIds) + 1) . '.' . $value;
            }
        } else {
            $this->identifications = explode(', ', $identifiers[0]);
        }
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public function getIdentifications(): array
    {
        return $this->identifications;
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
