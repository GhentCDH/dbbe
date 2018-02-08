<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class LocationController extends Controller
{
    use ArrayToJsonResponseTrait;

    /**
     * @Route("/cities/", name="cities")
     */
    public function getCities(Request $request)
    {
        return self::arrayToJsonResponse(
            $this->get('location_manager')->getAllCities()
        );
    }

    /**
     * @Route("/cities/{city_id}/libraries/", name="libraries")
     */
    public function getLibraries(Request $request, int $city_id)
    {
        return self::arrayToJsonResponse(
            $this->get('location_manager')->getLibrariesInCity($city_id)
        );
    }

    /**
     * @Route("/libraries/{library_id}/collections/", name="collections")
     */
    public function getCollections(Request $request, int $library_id)
    {
        return self::arrayToJsonResponse(
            $this->get('location_manager')->getCollectionsInLibrary($library_id)
        );
    }
}
