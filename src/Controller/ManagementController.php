<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\ManagementManager;
use App\Security\Roles;

class ManagementController extends BaseController
{
    public function __construct(ManagementManager $managementManager)
    {
        $this->manager = $managementManager;
        $this->templateFolder = 'Management/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/managements', name: 'managements_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
        return parent::getAll($request);
    }

    /**
     * @return Response
     */
    #[Route(path: '/managements/edit', name: 'managements_edit', methods: ['GET'])]
    public function edit(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder  . 'edit.html.twig',
            [
                'urls' => json_encode([
                    'managements_get' => $this->generateUrl('managements_get'),
                    'management_post' => $this->generateUrl('management_post'),
                    'management_put' => $this->generateUrl('management_put', ['id' => 'management_id']),
                    'management_delete' => $this->generateUrl('management_delete', ['id' => 'management_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'managements' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/managements', name: 'management_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/managements/{id}', name: 'management_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/managements/{id}', name: 'management_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
