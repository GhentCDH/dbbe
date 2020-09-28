<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\ObjectStorage\InstitutionManager;
use AppBundle\Utils\ArrayToJson;

class InstitutionController extends BaseController
{
    public function __construct(InstitutionManager $institutionManager)
    {
        $this->manager = $institutionManager;
    }

    /**
     * @Route("/institutions/regions/{id}", name="institution_deps_by_region")
     * @param int $id The region id
     * @param Request $request
     */
    public function getInstitutionsByRegion(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $institutions = $this->manager->getInstitutionsByRegion($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($institutions));
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/institutions", name="institution_post")
     * @Method("POST")
     * @param Request $request
     * @param bool $library Indicates whether the institution is a library
     * @param bool $monastery Indicates whether the institution is a library
     * @return JsonResponse
     */
    public function postInstitution(Request $request, bool $library = false, bool $monastery = false)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $institution = $this->manager->add(json_decode($request->getContent()), $library, $monastery);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($institution->getJson());
    }

    /**
     * @Route("/libraries", name="library_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postLibrary(Request $request)
    {
        return $this->postInstitution($request, true);
    }

    /**
     * @Route("/monasteries", name="monastery_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postMonastery(Request $request)
    {
        return $this->postInstitution($request, false, true);
    }

    /**
     * @Route("/institutions/{id}", name="institution_put")
     * @Method("PUT")
     * @param  int    $id institution id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Institution successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/libraries/{id}", name="library_put")
     * @Method("PUT")
     * @param  int    $id library id
     * @param Request $request
     * @return JsonResponse
     */
    public function putLibrary(int $id, Request $request)
    {
        return $this->put($id, $request);
    }

    /**
     * @Route("/monasteries/{id}", name="monastery_put")
     * @Method("PUT")
     * @param  int    $id monastery id
     * @param Request $request
     * @return JsonResponse
     */
    public function putMonastery(int $id, Request $request)
    {
        return $this->put($id, $request);
    }

    /**
     * @Route("/institutions/{id}", name="institution_delete")
     * @Method("DELETE")
     * @param int $id institution id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/libraries/{id}", name="library_delete")
     * @Method("DELETE")
     * @param int $id library id
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteLibrary(int $id, Request $request)
    {
        return $this->deleteInstitution($id, $request);
    }

    /**
     * @Route("/monasteries/{id}", name="monastery_delete")
     * @Method("DELETE")
     * @param int $id monastery id
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteMonastery(int $id, Request $request)
    {
        return $this->deleteInstitution($id, $request);
    }
}
