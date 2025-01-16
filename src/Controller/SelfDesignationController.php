<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\SelfDesignationManager;
use App\Security\Roles;

class SelfDesignationController extends BaseController
{
    public function __construct(SelfDesignationManager $selfDesignationManager)
    {
        $this->manager = $selfDesignationManager;
        $this->templateFolder = 'SelfDesignation/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/self-designations', name: 'self_designations_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        return parent::getAll($request);
    }

    /**
     * @return Response
     */
    #[Route(path: '/self-designations/edit', name: 'self_designations_edit', methods: ['GET'])]
    public function edit(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'SelfDesignation/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'self_designations_get' => $this->generateUrl('self_designations_get'),
                    'person_deps_by_self_designation' => $this->generateUrl('person_deps_by_self_designation', ['id' => 'self_designation_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'self_designation_post' => $this->generateUrl('self_designation_post'),
                    'self_designation_merge' => $this->generateUrl('self_designation_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'self_designation_put' => $this->generateUrl('self_designation_put', ['id' => 'self_designation_id']),
                    'self_designation_delete' => $this->generateUrl('self_designation_delete', ['id' => 'self_designation_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    // @codingStandardsIgnoreEnd
                ]),
                'selfDesignations' => json_encode(
                    $this->manager->getAllJson()
                ),
            ]
        );
    }

    /**
     * @param  int    $primaryId   first self designation id (will stay)
     * @param  int    $secondaryId second self designation id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/self-designations/{primaryId}/{secondaryId}', name: 'self_designation_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/self-designations', name: 'self_designation_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/self-designations/{id}', name: 'self_designation_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/self-designations/{id}', name: 'self_designation_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
