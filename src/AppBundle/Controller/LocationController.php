<?php

namespace AppBundle\Controller;

use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Helpers\ArrayToJsonTrait;

class LocationController extends Controller
{
    use ArrayToJsonTrait;

    /**
     * @Route("/locations", name="locations_get")
     * @param Request $request
     */
    public function getLocations(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $locations = $this->get('location_manager')->getAllCitiesLibrariesCollections();

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse($locations);
        }
        return $this->render(
            'AppBundle:Location:overview.html.twig',
            [
                'locations' => json_encode($locations),
            ]
        );
    }

    /**
     * @Route("/locations/region/{id}", name="locations_by_region")
     * @param int $id The region id
     * @param Request $request
     * */
    public function getLocationsByRegion(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $locations = $this
                ->get('location_manager')
                ->getLocationsByRegion($id);
            return new JsonResponse(self::arrayToShortJson($locations));
        }
        throw new Exception('Not implemented.');
    }
}
