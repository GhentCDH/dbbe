<?php

namespace App\Utils;

use Normalizer;

class GreekNormalizer
{
    /**
     * Removes accents and breathing marks from Greek text
     * @param string $input
     * @return string
     */
    public static function normalize($input): string
    {
        $encoded = urlencode(Normalizer::normalize($input, Normalizer::NFD));
        $stripped = preg_replace('/%C[^EF]%[0-9A-F]{2}/', '', $encoded);
        return mb_convert_case(urldecode($stripped), MB_CASE_LOWER);
    }
}