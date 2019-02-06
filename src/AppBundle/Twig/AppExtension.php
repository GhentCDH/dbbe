<?php

namespace AppBundle\Twig;

use DateTime;

use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleTest;

use AppBundle\Model\Image;
use AppBundle\Model\Person;
use AppBundle\Model\SubjectInterface;

class AppExtension extends Twig_Extension
{
    public function getTests()
    {
        return [
            new Twig_SimpleTest('person', function (SubjectInterface $object) {
                return $object instanceof Person;
            }),
            new Twig_SimpleTest('image', function ($object) {
                return $object instanceof Image;
            }),
        ];
    }

    public function getFilters()
    {
        return [
            new Twig_SimpleFilter('public', function ($object) {
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
            new Twig_SimpleFilter('name', function ($array) {
                return array_map(
                    function ($item) {
                        return $item->getName();
                    },
                    $array
                );
            }),
            new Twig_SimpleFilter('noUnknown', function ($array) {
                return array_filter(
                    $array,
                    function ($item) {
                        return $item->getName() !== 'Unknown';
                    }
                );
            }),
            new Twig_SimpleFilter('month', function ($string) {
                return DateTime::createFromFormat('m', substr($string, 5, 2))->format('M');
            }),
            new Twig_SimpleFilter('day', function ($string) {
                return substr($string, 8, 2);
            }),
        ];
    }
}
