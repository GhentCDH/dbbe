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

use AppBundle\Model\Status;

class StatusController extends Controller
{
    /**
     * @Route("/statuses", name="statuses_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getStatuses(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            if (!empty($request->query->get('type'))) {
                switch($request->query->get('type')) {
                case 'occurrence':
                    return new JsonResponse(
                        array_merge(
                            $this->get('status_manager')->getByTypeJson(Status::OCCURRENCE_TEXT),
                            $this->get('status_manager')->getByTypeJson(Status::OCCURRENCE_RECORD),
                            $this->get('status_manager')->getByTypeJson(Status::OCCURRENCE_DIVIDED),
                            $this->get('status_manager')->getByTypeJson(Status::OCCURRENCE_SOURCE)
                        )
                    );
                    break;
                case 'manuscript':
                    return new JsonResponse(
                        $this->get('status_manager')->getByTypeJson(Status::MANUSCRIPT)
                    );
                    break;
                case 'type':
                    return new JsonResponse(
                        array_merge(
                            $this->get('status_manager')->getByTypeJson(Status::TYPE_CRITICAL),
                            $this->get('status_manager')->getByTypeJson(Status::TYPE_TEXT)
                        )
                    );
                    break;
                }
            } else {
                return new JsonResponse(
                    $this->get('status_manager')->getAllJson()
                );
            }
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/statuses/edit", name="statuses_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editStatuses(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Status:edit.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'urls' => json_encode([
                    'statuses_get' => $this->generateUrl('statuses_get'),
                    'manuscript_deps_by_status' => $this->generateUrl('manuscript_deps_by_status', ['id' => 'status_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'occurrence_deps_by_status' => $this->generateUrl('occurrence_deps_by_status', ['id' => 'status_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_status' => $this->generateUrl('type_deps_by_status', ['id' => 'status_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'status_post' => $this->generateUrl('status_post'),
                    'status_put' => $this->generateUrl('status_put', ['id' => 'status_id']),
                    'status_delete' => $this->generateUrl('status_delete', ['id' => 'status_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'statuses' => json_encode(
                    $this->get('status_manager')->getAllJson()
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * @Route("/statuses", name="status_post")
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
