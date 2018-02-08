<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ArrayToJsonResponseTrait
{
    public static function arrayToJsonResponse(array $array): JsonResponse
    {
        $result = [];
        foreach ($array as $item) {
            $result[] = $item->getJson();
        }

        return new JsonResponse($result);
    }
}
