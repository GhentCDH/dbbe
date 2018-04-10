<?php

namespace AppBundle\Controller;

use Exception;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Helpers\ArrayToJsonTrait;

class RegionController extends Controller
{
    use ArrayToJsonTrait;

    /**
     * @Route("/regions/regions/{id}", name="regions_by_region")
     * @param int $id The region id
     * @param Request $request
     */
    public function getRegionByRegion(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $regionsWithParents = $this
                ->get('region_manager')
                ->getRegionsWithParentsByRegion($id);
            return new JsonResponse(self::arrayToShortJson($regionsWithParents));
        }
        throw new Exception('Not implemented.');
    }

    /**
     * @Route("/regions", name="regions_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getRegions(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $regions = array_values(
            self::arrayToJson(
                $this->get('region_manager')->getAllRegionsWithParents()
            )
        );

        // sort on name
        usort($regions, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse($regions);
        }
        return $this->render(
            'AppBundle:Region:overview.html.twig',
            [
                'regions' => json_encode($regions),
            ]
        );
    }

    /**
     * @Route("/regions", name="region_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postRegion(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $region = $this
                ->get('region_manager')
                ->addRegionWithParents(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($region->getJson());
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
            ->updateRegionWithParents($id, json_decode($request->getContent()));

        if (empty($region)) {
            throw $this->createNotFoundException('There is no region with the requested id.');
        }

        return new JsonResponse($region->getJson());
    }

    /**
     * @Route("/regions/{id}", name="region_delete")
     * @Method("DELETE")
     * @param  int    $id region id
     * @return JsonResponse
     */
    public function deleteRegion(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $this
                ->get('region_manager')
                ->delRegion($id);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
