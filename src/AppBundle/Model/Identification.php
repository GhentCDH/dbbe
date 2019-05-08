<?php

namespace AppBundle\Model;

use AppBundle\Utils\RomanFormatter;

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
                $identification->extra = $identifier->getExtra() ? $itemExtras[$index] : null;
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
        return RomanFormatter::numberToRoman($this->volume);
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function __toString(): String
    {
        return (isset($this->volume) ? RomanFormatter::numberToRoman($this->volume) . '.' : '')
            . $this->identification
            . (!empty($this->extra) ? ': "' . $this->extra . '"' : '');
    }

    public function getVolumeIdentification(): String
    {
        return (isset($this->volume) ? RomanFormatter::numberToRoman($this->volume) . '.' : '')
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
}
