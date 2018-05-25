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

        $statuses = ArrayToJson::arrayToJson(
            $this->get('status_manager')->getAllStatuses()
        );

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse($statuses);
        }
        return $this->render(
            'AppBundle:Status:overview.html.twig',
            [
                'statuses' => json_encode($statuses),
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
