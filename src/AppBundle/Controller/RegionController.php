<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RegionController extends Controller
{
    /**
     * @Route("/regions", name="regions_get")
     * @param Request $request
     */
    public function getLocations(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $regions = $this->get('region_manager')->getAllRegionsWithParents();

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse($regions);
        }
        return $this->render(
            'AppBundle:Location:overview.html.twig',
            [
                'locations' => json_encode($regions),
            ]
        );
    }

    /**
     * @Route("/regions/{id}", name="region_put")
     * @Method("PUT")
     * @param  int    $id region id
     * @param Request $request
     * @return JsonResponse
     */
    public function putRegion(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $region = $this
            ->get('region_manager')
            ->updateRegion($id, json_decode($request->getContent()));

        if (empty($region)) {
            throw $this->createNotFoundException('There is no region with the requested id.');
        }

        return new JsonResponse($region->getJson());
    }

    /**
     * @Route("/regions", name="region_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postRegion(Request $request)
    {
        throw new \Exception('Not implemented.');
    }
}
