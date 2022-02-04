<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\StatusManager;

use App\Model\Status;

class StatusController extends BaseController
{
    public function __construct(StatusManager $statusManager)
    {
        $this->manager = $statusManager;
    }

    /**
     * @Route("/statuses", name="statuses_get", methods={"GET"})
     * @param Request $request
     */
    public function getStatuses(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            if (!empty($request->query->get('type'))) {
                switch ($request->query->get('type')) {
                    case 'occurrence':
                        return new JsonResponse(
                            array_merge(
                                $this->manager->getByTypeJson(Status::OCCURRENCE_TEXT),
                                $this->manager->getByTypeJson(Status::OCCURRENCE_RECORD),
                                $this->manager->getByTypeJson(Status::OCCURRENCE_DIVIDED),
                                $this->manager->getByTypeJson(Status::OCCURRENCE_SOURCE)
                            )
                        );
                        break;
                    case 'manuscript':
                        return new JsonResponse(
                            $this->manager->getByTypeJson(Status::MANUSCRIPT)
                        );
                        break;
                    case 'type':
                        return new JsonResponse(
                            array_merge(
                                $this->manager->getByTypeJson(Status::TYPE_CRITICAL),
                                $this->manager->getByTypeJson(Status::TYPE_TEXT)
                            )
                        );
                        break;
                }
            } else {
                return new JsonResponse(
                    $this->manager->getAllJson()
                );
            }
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/statuses/edit", name="statuses_edit", methods={"GET"})
     * @return Response
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'Status/edit.html.twig',
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
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('app_login') : $this->generateUrl('saml_login'),
                ]),
                'statuses' => json_encode(
                    $this->manager->getAllJson()
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * @Route("/statuses", name="status_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Status added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/statuses/{id}", name="status_put", methods={"PUT"})
     * @param  int    $id status id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/statuses/{id}", name="status_delete", methods={"DELETE"})
     * @param int $id status id
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteStatus(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
