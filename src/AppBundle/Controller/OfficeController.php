<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class OfficeController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'office_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Office:';

    /**
     * @Route("/offices", name="offices_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * Get all offices that have a dependency on an office
     * (occupation ->idparentoccupation)
     * @Route("/offices/offices/{id}", name="office_deps_by_office")
     * @Method("GET")
     * @param int     $id      office id
     * @param Request $request
     */
    public function getDepsByOffice(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOfficeDependencies');
    }

    /**
     * Get all offices that have a dependency on a region
     * (occupation -> idregion)
     * @Route("/offices/regions/{id}", name="office_deps_by_region")
     * @Method("GET")
     * @param int    $id      region id
     * @param Request $request
     */
    public function getDepsByRegion(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * @Route("/offices/edit", name="offices_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
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
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'data'=> json_encode([
                    'offices' => ArrayToJson::arrayToJson(
                        $this->get('office_manager')->getAll()
                    ),
                    'regions' => ArrayToJson::arrayToShortJson(
                        $this->get('region_manager')->getAll()
                    ),
                ]),
            ]
        );
    }

    /**
     * @Route("/offices", name="office_post")
     * @Method("POST")
     * @param  Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/offices/{primaryId}/{secondaryId}", name="office_merge")
     * @Method("PUT")
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
     * @Route("/offices/{id}", name="office_put")
     * @Method("PUT")
     * @param  int     $id office id
     * @param  Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/offices/{id}", name="office_delete")
     * @Method("DELETE")
     * @param  int     $id office id
     * @param  Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
