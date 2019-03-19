<?php

namespace AppBundle\Twig;

use DateTime;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

use AppBundle\Model\Image;
use AppBundle\Model\Person;
use AppBundle\Model\SubjectInterface;

class AppExtension extends AbstractExtension
{
    public function getTests()
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

    public function getFilters()
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
                return DateTime::createFromFormat('m', substr($string, 5, 2))->format('M');
            }),
            new TwigFilter('day', function (string $string) {
                return substr($string, 8, 2);
            }),
        ];
    }
}
