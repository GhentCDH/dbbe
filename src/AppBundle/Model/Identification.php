<?php

namespace AppBundle\Model;

class Identification
{
    protected $identifier;
    protected $identifications;
    protected $volume;
    protected $extra;

    public static function constructFromDB(
        Identifier $identifier,
        array $identifications,
        array $volumes,
        array $extras
    ) {
        $identification = new Identification();

        $identification->identifier = $identifier;
        $identification->identifications = [];
        $values = array_map(null, $identifications, $volumes, $extras);
        foreach ($values as $item) {
            $identification->identifications[] =
                (isset($item[1]) ? self::numberToRoman($item[1]) . '.' : '')
                . $item[0]
                . (isset($item[2]) ? ' (' . $item[2] . ')' : '');
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

    public function getVolume(): ?int
    {
        return $this->volume;
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
