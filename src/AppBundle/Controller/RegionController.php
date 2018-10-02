<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class RegionController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'region_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Region:';

    /**
     * @Route("/regions", name="regions_get")
     * @Method("GET")
     * @param Request $request
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
     * @Route("/regions/edit", name="regions_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editRegions(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Region:edit.html.twig',
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
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'regions' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('region_manager')->getAll()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/regions", name="region_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/regions/{primaryId}/{secondaryId}", name="region_merge")
     * @Method("PUT")
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
     * @Route("/regions/{id}", name="region_put")
     * @Method("PUT")
     * @param  int    $id region id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/regions/{id}", name="region_delete")
     * @Method("DELETE")
     * @param int     $id office id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
