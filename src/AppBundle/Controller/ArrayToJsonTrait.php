<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ArrayToJsonTrait
{
    public static function arrayToJson(array $array): array
    {
        return array_map(
            function ($item) {
                return $item->getJson();
            },
            $array
        );
    }

    public static function arrayToJsonResponse(array $array): JsonResponse
    {
        return new JsonResponse(self::arrayToJson($array));
    }
}
