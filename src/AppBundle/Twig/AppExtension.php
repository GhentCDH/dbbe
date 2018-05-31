<?php

namespace AppBundle\Twig;

use AppBundle\Model\Person;
use AppBundle\Model\SubjectInterface;

class AppExtension extends \Twig_Extension
{
    public function getTests()
    {
        return [
            new \Twig_SimpleTest('person', function (SubjectInterface $object) {
                return $object instanceof Person;
            }),
        ];
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('public', function ($object) {
                if (is_array($object)) {
                    return array_filter($object, function ($item) {
                        return $item->getPublic();
                    });
                } else {
                    if ($object->getPublic()) {
                        return $object;
                    } else {
                        return null;
                    }
                }
            }),
        ];
    }
}
