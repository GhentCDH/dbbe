<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RegionController extends Controller
{
    /**
     * @Route("/cities/", name="cities")
     */
    public function getCities(Request $request)
    {
        $citiesWithParents = $this->get('region_manager')->getAllCitiesWithParents();

        $result = [];
        foreach ($citiesWithParents as $cityWithParents) {
            $result[] = $cityWithParents->getJson();
        }

        return new JsonResponse($result);
    }
}
