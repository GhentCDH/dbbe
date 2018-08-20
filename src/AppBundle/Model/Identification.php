<?php

namespace AppBundle\Model;

class Identification
{
    use CacheObjectTrait;

    private $identifier;
    private $identifications;

    public static function constructFromDB(Identifier $identifier, array $identifications, array $authorityIds, array $identificationIds)
    {
        $identification = new Identification();

        $identification->identifier = $identifier;
        $identification->identifications = [];
        if (count($identificationIds) > 1) {
            foreach ($identifications as $key => $value) {
                $identification->identifications[] = self::numberToRoman(array_search($authorityIds[$key], $identificationIds) + 1) . '.' . $value;
            }
        } else {
            $identification->identifications = explode(', ', $identifications[0]);
        }

        return $identification;
    }

    private static function constructFromCache(Identifier $identifier, array $identifications)
    {
        $identification = new Identification();

        $identification->identifier = $identifier;
        $identification->identifications = $identifications;

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


    public static function unlinkCache($data)
    {
        $identification = self::constructFromCache($data['identifier'], $data['identifications']);

        foreach ($data as $key => $value) {
            $identification->set($key, $value);
        }

        return $identification;
    }
}
