<?php

namespace App\Twig;

use Twig\TwigFilter;
use Twig\TwigTest;
use DateTime;

use Twig\Extension\AbstractExtension;


use App\Model\Image;
use App\Model\Person;
use App\Model\SubjectInterface;

class AppExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            new TwigTest('person', function (SubjectInterface $object) {
                return $object instanceof Person;
            }),
            new TwigTest('image', function ($object) {
                return $object instanceof Image;
            }),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('public', function ($object) {
                if (is_array($object)) {
                    return array_filter($object, function ($item) {
                        return !method_exists($item, 'getPublic') || $item->getPublic();
                    });
                } else {
                    if (!method_exists($object, 'getPublic') || $object->getPublic()) {
                        return $object;
                    } else {
                        return null;
                    }
                }
            }),
            new TwigFilter('name', function (array $array) {
                return array_map(
                    function ($item) {
                        return $item->getName();
                    },
                    $array
                );
            }),
            new TwigFilter('noUnknown', function (array $array) {
                return array_filter(
                    $array,
                    function ($item) {
                        return $item->getName() !== 'Unknown';
                    }
                );
            }),
            new TwigFilter('notVassis', function (array $array) {
                return array_filter(
                    $array,
                    function ($item) {
                        return $item->getIdentifier()->getSystemName() !== 'vassis';
                    }
                );
            }),
            new TwigFilter('month', function (string $string) {
                return DateTime::createFromFormat('Y-m-d', $string)->format('M');
            }),
            new TwigFilter('day', function (string $string) {
                return DateTime::createFromFormat('Y-m-d', $string)->format('d');
            }),
            new TwigFilter('breakAtCapitals', function (string $string) {
                return preg_replace('([A-Z])', strtolower(" $0"), $string);
            }),
        ];
    }
}
