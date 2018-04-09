<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InstitutionController extends Controller
{
    /**
     * @Route("/institutions/", name="institutions_post")
     * @Method("POST")
     * @param Request $request
     * @param bool $library Indicates whether the institution is a library
     * @return JsonResponse
     */
    public function postInstitutions(Request $request, bool $library = false)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $institution = $this
                ->get('institution_manager')
                ->addInstitution(json_decode($request->getContent()), $library);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($institution->getJson());
    }

    /**
     * @Route("/libraries/", name="libraries_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postLibraries(Request $request)
    {
        return $this->postInstitutions($request, true);
    }

    /**
     * @Route("/institutions/{id}", name="institution_put")
     * @Method("PUT")
     * @param  int    $id institution id
     * @param Request $request
     * @return JsonResponse
     */
    public function putInstitution(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $institution = $this
                ->get('institution_manager')
                ->updateInstitution($id, json_decode($request->getContent()));
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

        return new JsonResponse($institution->getJson());
    }

    /**
     * @Route("/libraries/{id}", name="library_put")
     * @Method("PUT")
     * @param  int    $id library id
     * @param Request $request
     * @return JsonResponse
     */
    public function pubLibrary(int $id, Request $request)
    {
        return $this->putInstitution($id, $request);
    }

    /**
     * @Route("/institutions/{id}", name="institution_delete")
     * @Method("DELETE")
     * @param  int    $id institution id
     * @return JsonResponse
     */
    public function deleteInstitution(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $this
                ->get('institution_manager')
                ->delInstitution($id);
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

    /**
     * @Route("/libraries/{id}", name="library_delete")
     * @Method("DELETE")
     * @param  int    $id library id
     * @return JsonResponse
     */
    public function deleteLibrary(int $id)
    {
        return $this->deleteInstitution($id);
    }
}
