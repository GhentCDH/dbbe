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
     * @Route("/regions", name="regions_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * Get all regions that have a dependency on a region
     * (region -> parent_idregion)
     * @Route("/regions/regions/{id}", name="region_deps_by_region")
     * @param int     $id      region id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByRegion(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * @Route("/regions/edit", name="regions_edit", methods={"GET"})
     * @return Response
     */
    public function editRegions()
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
     * @Route("/regions", name="region_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/regions/{primaryId}/{secondaryId}", name="region_merge", methods={"PUT"})
     * @param  int    $primaryId   first region id (will stay)
     * @param  int    $secondaryId second region id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/regions/{id}", name="region_put", methods={"PUT"})
     * @param  int    $id region id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/regions/{id}", name="region_delete", methods={"DELETE"})
     * @param int     $id office id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
