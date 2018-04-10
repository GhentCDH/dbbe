<?php

namespace AppBundle\Helpers;

trait ArrayToJsonTrait
{
    public static function arrayToShortJson(array $array): array
    {
        return array_map(
            function ($item) {
                return $item->getShortJson();
            },
            $array
        );
    }

    public static function arrayToJson(array $array): array
    {
        return array_map(
            function ($item) {
                return $item->getJson();
            },
            $array
        );
    }
}
