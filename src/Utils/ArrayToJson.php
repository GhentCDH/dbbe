<?php

namespace App\Utils;

class ArrayToJson
{
    public static function arrayToShortJson(array $array): array
    {
        return  array_values(
            array_map(
                function ($item) {
                    return $item->getShortJson();
                },
                $array
            )
        );
    }

    public static function arrayToJson(array $array): array
    {
        return array_values(
            array_map(
                function ($item) {
                    return $item->getJson();
                },
                $array
            )
        );
    }

    public static function arrayToShortHistoricalJson(array $array): array
    {
        return  array_values(
            array_map(
                function ($item) {
                    return $item->getShortHistoricalJson();
                },
                $array
            )
        );
    }

    public static function arrayToShortElastic(array $array): array
    {
        return array_values(
            array_map(
                function ($item) {
                    return $item->getShortElastic();
                },
                $array
            )
        );
    }
}
