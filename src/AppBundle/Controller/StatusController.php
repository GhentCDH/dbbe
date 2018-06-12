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

class StatusController extends Controller
{
    /**
     * @Route("/statuses", name="statuses_get")
     * @param Request $request
     */
    public function getStatuses(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson(
                    $this->get('status_manager')->getAllStatuses()
                )
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/statuses/edit", name="statuses_edit")
     * @param Request $request
     */
    public function editStatuses(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->render(
            'AppBundle:Status:edit.html.twig',
            [
                'urls' => json_encode([
                    'statuses_get' => $this->generateUrl('statuses_get'),
                    'manuscript_deps_by_status' => $this->generateUrl('manuscript_deps_by_status', ['id' => 'status_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'status_post' => $this->generateUrl('status_post'),
                    'status_put' => $this->generateUrl('status_put', ['id' => 'status_id']),
                    'status_delete' => $this->generateUrl('status_delete', ['id' => 'status_id']),
                ]),
                'statuses' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('status_manager')->getAllStatuses()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/statuses/", name="status_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postStatus(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $status = $this
                ->get('status_manager')
                ->addStatus(json_decode($request->getContent()));
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($status->getJson());
    }

    /**
     * @Route("/statuses/{id}", name="status_put")
     * @Method("PUT")
     * @param  int    $id status id
     * @param Request $request
     * @return JsonResponse
     */
    public function putStatus(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $status = $this
                ->get('status_manager')
                ->updateStatus($id, json_decode($request->getContent()));
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

        return new JsonResponse($status->getJson());
    }

    /**
     * @Route("/statuses/{id}", name="status_delete")
     * @Method("DELETE")
     * @param  int    $id status id
     * @return JsonResponse
     */
    public function deleteStatus(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $this
                ->get('status_manager')
                ->delStatus($id);
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
