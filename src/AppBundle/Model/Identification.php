<?php

namespace AppBundle\Model;

class Identification
{
    protected $identifier;
    protected $identification;
    protected $volume;
    protected $extra;

    /**
     * @param Identifier $identifier
     * @param array $identifications
     * @param array $volumes
     * @param array $extras
     * @return array Structure: Identifier $identifier, array $identifications
     */
    public static function constructFromDB(
        Identifier $identifier,
        array $identifications,
        array $volumes,
        array $extras
    ) {
        $result = [$identifier, []];

        $values = array_map(null, $identifications, $volumes, $extras);

        foreach ($values as $item) {
            $identification = new Identification();
            $identification->identifier = $identifier;
            $identification->identification = $item[0];
            $identification->volume = $item[1];
            $identification->extra = $item[2];
            $result[1][] = $identification;
        }

        return $result;
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public function getIdentification(): ?string
    {
        return $this->identification;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function getRomanVolume(): ?string
    {
        if (!isset($this->volume)) {
            return null;
        }
        return self::numberToRoman($this->volume);
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function __toString(): String
    {
        return (isset($this->volume) ? self::numberToRoman($this->volume) . '.' : '')
            . $this->identification
            . (isset($this->extra) ? ': "' . $this->extra . '"' : '');
    }

    public function getVolumeIdentification(): String
    {
        return (isset($this->volume) ? self::numberToRoman($this->volume) . '.' : '')
            . $this->identification;
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
