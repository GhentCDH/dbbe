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

use AppBundle\Utils\ArrayToJson;

class RegionController extends Controller
{
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
            return new JsonResponse(ArrayToJson::arrayToShortJson($regionsWithParents));
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

        $regionsWithParents = $this->get('region_manager')->getAllRegionsWithParents();

        // sort on name
        usort($regionsWithParents, ['AppBundle\Model\RegionWithParents', 'sortByNameHistoricalName']);

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(ArrayToJson::arrayToJson($regionsWithParents));
        }
        return $this->render(
            'AppBundle:Region:overview.html.twig',
            [
                'regions' => json_encode(ArrayToJson::arrayToJson($regionsWithParents)),
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
            $regionWithParents = $this
                ->get('region_manager')
                ->addRegionWithParents(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($regionWithParents->getJson());
    }

    /**
     * @Route("/regions/{primary}/{secondary}", name="region_merge")
     * @Method("PUT")
     * @param  int    $primary first region id (will stay)
     * @param  int    $secondary second region id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function mergeRegions(int $primary, int $secondary, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $regionWithParents = $this
                ->get('region_manager')
                ->mergeRegionsWithParents($primary, $secondary);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse($regionWithParents->getJson());
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

        try {
            $regionWithParents = $this
                ->get('region_manager')
                ->updateRegionWithParents($id, json_decode($request->getContent()));
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($regionWithParents->getJson());
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
