<?php

namespace AppBundle\Controller;

use AppBundle\Utils\ArrayToJson;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class OnlineSourceController extends BasicController
{
    /**
     * @var string
     */
    const MANAGER = 'online_source_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:OnlineSource:';

    /**
     * @Route("/onlinesources/add", name="online_source_add")
     * @Method("GET")
     * @param Request $request
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/onlinesources/{id}", name="online_source_get")
     * @Method("GET")
      * @param int     $id
      * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all online sources that have a dependency on an instutution
     * @Route("/onlinesources/institutions/{id}", name="online_source_deps_by_institution")
     * @Method("GET")
     * @param  int    $id institution id
     * @param Request $request
     */
    public function getDepsByInstitution(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getInstitutionDependencies');
    }

    /**
     * @Route("/onlinesources", name="online_source_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Online source added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/onlinesources/{id}", name="online_source_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Online source data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/onlinesources/{id}", name="online_source_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/onlinesources/{id}/edit", name="online_source_edit")
     * @Method("GET")
     * @param  int|null $id
     * @param Request $request
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'online_source_get' => $this->generateUrl('online_source_get', ['id' => $id == null ? 'online_source_id' : $id]),
                    'online_source_post' => $this->generateUrl('online_source_post'),
                    'online_source_put' => $this->generateUrl('online_source_put', ['id' => $id == null ? 'online_source_id' : $id]),
                    // @codingStandardsIgnoreEnd
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'onlineSource' => empty($id)
                        ? null
                        : $this->get(self::MANAGER)->getFull($id)->getJson(),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('identifier_manager')->getIdentifiersByType('onlineSource')
                    )
                ),
            ]
        );
    }
}