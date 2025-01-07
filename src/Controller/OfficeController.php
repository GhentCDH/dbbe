<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\OfficeManager;
use App\ObjectStorage\RegionManager;
use App\Security\Roles;

class OfficeController extends BaseController
{
    public function __construct(OfficeManager $officeManager)
    {
        $this->manager = $officeManager;
        $this->templateFolder = 'Office/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/offices', name: 'offices_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        return parent::getAll($request);
    }

    /**
     * Get all offices that have a dependency on an office
     * (occupation ->idparentoccupation)
     * @param int $id office id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/offices/offices/{id}', name: 'office_deps_by_office', methods: ['GET'])]
    public function getDepsByOffice(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getOfficeDependencies');
    }

    /**
     * Get all offices that have a dependency on a region
     * (occupation -> idregion)
     * @param int $id region id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/offices/regions/{id}', name: 'office_deps_by_region', methods: ['GET'])]
    public function getDepsByRegion(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * @param RegionManager $regionManager
     * @return Response
     */
    #[Route(path: '/offices/edit', name: 'offices_edit', methods: ['GET'])]
    public function edit(
        RegionManager $regionManager
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'offices_get' => $this->generateUrl('offices_get'),
                    'office_deps_by_office' => $this->generateUrl('office_deps_by_office', ['id' => 'office_id']),
                    'person_deps_by_office' => $this->generateUrl('person_deps_by_office', ['id' => 'office_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'office_post' => $this->generateUrl('office_post'),
                    'office_merge' => $this->generateUrl('office_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'office_put' => $this->generateUrl('office_put', ['id' => 'office_id']),
                    'office_delete' => $this->generateUrl('office_delete', ['id' => 'office_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    // @codingStandardsIgnoreEnd
                ]),
                'data'=> json_encode([
                    'offices' => $this->manager->getAllJson(),
                    'regions' => $regionManager->getAllShortHistoricalJson(),
                ]),
            ]
        );
    }

    /**
     * @param  Request $request
     * @return JsonResponse
     */
    #[Route(path: '/offices', name: 'office_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int     $primaryId   first office id (will stay)
     * @param  int     $secondaryId second office id (will be deleted)
     * @param  Request $request
     * @return JsonResponse
     */
    #[Route(path: '/offices/{primaryId}/{secondaryId}', name: 'office_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param  int     $id office id
     * @param  Request $request
     * @return JsonResponse
     */
    #[Route(path: '/offices/{id}', name: 'office_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int     $id office id
     * @param  Request $request
     * @return JsonResponse
     */
    #[Route(path: '/offices/{id}', name: 'office_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
