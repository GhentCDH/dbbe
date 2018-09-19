<?php

namespace AppBundle\Twig;

use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleTest;

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
        ];
    }
}
