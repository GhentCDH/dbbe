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

class OccupationController extends Controller
{
    /**
     * @Route("/occupations", name="occupations_get")
     * @param Request $request
     */
    public function getOccupations(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson(
                    $this->get('occupation_manager')->getAllOccupations()
                )
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/occupations/edit", name="occupations_edit")
     * @param Request $request
     */
    public function editOccupations(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Occupation:edit.html.twig',
            [
                'urls' => json_encode([
                    'occupations_get' => $this->generateUrl('occupations_get'),
                    'person_deps_by_occupation' => $this->generateUrl('person_deps_by_occupation', ['id' => 'occupation_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'occupation_post' => $this->generateUrl('occupation_post'),
                    'occupation_put' => $this->generateUrl('occupation_put', ['id' => 'occupation_id']),
                    'occupation_delete' => $this->generateUrl('occupation_delete', ['id' => 'occupation_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'occupations' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('occupation_manager')->getAllOccupations()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/occupations/", name="occupation_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postOccupation(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $occupation = $this
                ->get('occupation_manager')
                ->addOccupation(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($occupation->getJson());
    }

    /**
     * @Route("/occupations/{id}", name="occupation_put")
     * @Method("PUT")
     * @param  int    $id occupation id
     * @param Request $request
     * @return JsonResponse
     */
    public function putOccupation(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $occupation = $this
                ->get('occupation_manager')
                ->updateOccupation($id, json_decode($request->getContent()));
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

        return new JsonResponse($occupation->getJson());
    }

    /**
     * @Route("/occupations/{id}", name="occupation_delete")
     * @Method("DELETE")
     * @param  int    $id occupation id
     * @return JsonResponse
     */
    public function deleteOccupation(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $this
                ->get('occupation_manager')
                ->delOccupation($id);
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
