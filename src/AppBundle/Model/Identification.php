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
            $itemIdentifications = explode('|', $item[0]);
            $itemExtras = explode('|', $item[2]);
            foreach (array_keys($itemIdentifications) as $index) {
                $identification = new Identification();
                $identification->identifier = $identifier;
                $identification->identification = $itemIdentifications[$index];
                $identification->volume = $item[1];
                $identification->extra = $itemExtras[$index];
                $result[1][] = $identification;
            }
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
            . (!empty($this->extra) ? ': "' . $this->extra . '"' : '');
    }

    public function getVolumeIdentification(): String
    {
        return (isset($this->volume) ? self::numberToRoman($this->volume) . '.' : '')
            . $this->identification;
    }

    public function getJson(): array
    {
        $result = [
            'identification' => $this->identification,
        ];
        if (!empty($this->volume)) {
            $result['volume'] = $this->volume;
        }
        if (!empty($this->extra)) {
            $result['extra'] = $this->extra;
        }

        return $result;
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
