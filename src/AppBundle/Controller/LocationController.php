<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class LocationController extends Controller
{
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
}
