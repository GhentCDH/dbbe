<?php

namespace App\Utils;

class VolumeSortKey
{

    /**
     * Zero pad all parts of a book volume
     * @param string $volume
     * @return string
     */
    public static function sortKey($volume)
    {
        $parts = explode('.', $volume);
        foreach ($parts as $part) {
            $part = str_pad($part, 10, '0', STR_PAD_LEFT);
        }
        return implode('.', $parts);
    }
}