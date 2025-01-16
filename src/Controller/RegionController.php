<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\RegionManager;
use App\Security\Roles;

class RegionController extends BaseController
{
    public function __construct(RegionManager $regionManager)
    {
        $this->manager = $regionManager;
        $this->templateFolder = 'Region/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/regions', name: 'regions_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        return parent::getAll($request);
    }

    /**
     * Get all regions that have a dependency on a region
     * (region -> parent_idregion)
     * @param int     $id      region id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/regions/regions/{id}', name: 'region_deps_by_region')]
    public function getDepsByRegion(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * @return Response
     */
    #[Route(path: '/regions/edit', name: 'regions_edit', methods: ['GET'])]
    public function editRegions(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Region/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'regions_get' => $this->generateUrl('regions_get'),
                    'manuscript_deps_by_region' => $this->generateUrl('manuscript_deps_by_region', ['id' => 'region_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'institution_deps_by_region' => $this->generateUrl('institution_deps_by_region', ['id' => 'region_id']),
                    'office_deps_by_region' => $this->generateUrl('office_deps_by_region', ['id' => 'region_id']),
                    'person_deps_by_region' => $this->generateUrl('person_deps_by_region', ['id' => 'region_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'region_deps_by_region' => $this->generateUrl('region_deps_by_region', ['id' => 'region_id']),
                    'region_post' => $this->generateUrl('region_post'),
                    'region_merge' => $this->generateUrl('region_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'region_put' => $this->generateUrl('region_put', ['id' => 'region_id']),
                    'region_delete' => $this->generateUrl('region_delete', ['id' => 'region_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    // @codingStandardsIgnoreEnd
                ]),
                'regions' => json_encode(
                    $this->manager->getAllJson()
                ),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/regions', name: 'region_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $primaryId   first region id (will stay)
     * @param  int    $secondaryId second region id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/regions/{primaryId}/{secondaryId}', name: 'region_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param  int    $id region id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/regions/{id}', name: 'region_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param int     $id office id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/regions/{id}', name: 'region_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
