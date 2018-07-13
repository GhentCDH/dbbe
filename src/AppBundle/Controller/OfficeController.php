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

use AppBundle\Utils\ArrayToJson;

class OfficeController extends Controller
{
    /**
     * @Route("/offices", name="offices_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getOffices(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson(
                    $this->get('office_manager')->getAllOffices()
                )
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/offices/edit", name="offices_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editOffices(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Office:edit.html.twig',
            [
                'urls' => json_encode([
                    'offices_get' => $this->generateUrl('offices_get'),
                    'person_deps_by_office' => $this->generateUrl('person_deps_by_office', ['id' => 'office_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'office_post' => $this->generateUrl('office_post'),
                    'office_put' => $this->generateUrl('office_put', ['id' => 'office_id']),
                    'office_delete' => $this->generateUrl('office_delete', ['id' => 'office_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'offices' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('office_manager')->getAllOffices()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/offices", name="office_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postOffice(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $office = $this
                ->get('office_manager')
                ->addOffice(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($office->getJson());
    }

    /**
     * @Route("/offices/{id}", name="office_put")
     * @Method("PUT")
     * @param  int    $id office id
     * @param Request $request
     * @return JsonResponse
     */
    public function putOffice(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $office = $this
                ->get('office_manager')
                ->updateOffice($id, json_decode($request->getContent()));
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

        return new JsonResponse($office->getJson());
    }

    /**
     * @Route("/offices/{id}", name="office_delete")
     * @Method("DELETE")
     * @param  int    $id office id
     * @return JsonResponse
     */
    public function deleteOffice(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $this
                ->get('office_manager')
                ->delOffice($id);
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
