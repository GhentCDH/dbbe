<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Utils\ArrayToJson;

class LocationController extends Controller
{
    /**
     * @Route("/locations/manuscripts", name="locations_manuscripts_get")
     * @param Request $request
     */
    public function getLocationsForManuscripts(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson($this->get('location_manager')->getLocationsForManuscripts())
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/locations", name="locations_get")
     * @param Request $request
     */
    public function getLocations(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $locations = ArrayToJson::arrayToJson(
            $this->get('location_manager')->getLocationsForLocations()
        );

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
