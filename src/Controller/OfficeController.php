<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\OfficeManager;
use App\ObjectStorage\RegionManager;
use Symfony\Component\HttpFoundation\Response;

class OfficeController extends BaseController
{
    public function __construct(OfficeManager $officeManager)
    {
        $this->manager = $officeManager;
        $this->templateFolder = 'Office/';
    }

    /**
     * @Route("/offices", name="offices_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * Get all offices that have a dependency on an office
     * (occupation ->idparentoccupation)
     * @Route("/offices/offices/{id}", name="office_deps_by_office", methods={"GET"})
     * @param int $id office id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByOffice(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOfficeDependencies');
    }

    /**
     * Get all offices that have a dependency on a region
     * (occupation -> idregion)
     * @Route("/offices/regions/{id}", name="office_deps_by_region", methods={"GET"})
     * @param int $id region id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByRegion(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * @Route("/offices/edit", name="offices_edit", methods={"GET"})
     * @param RegionManager $regionManager
     * @return Response
     */
    public function edit(
        RegionManager $regionManager
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

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
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('idci_keycloak_security_auth_connect') : $this->generateUrl('saml_login'),
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
     * @Route("/offices", name="office_post", methods={"POST"})
     * @param  Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/offices/{primaryId}/{secondaryId}", name="office_merge", methods={"PUT"})
     * @param  int     $primaryId   first office id (will stay)
     * @param  int     $secondaryId second office id (will be deleted)
     * @param  Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/offices/{id}", name="office_put", methods={"PUT"})
     * @param  int     $id office id
     * @param  Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/offices/{id}", name="office_delete", methods={"DELETE"})
     * @param  int     $id office id
     * @param  Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
