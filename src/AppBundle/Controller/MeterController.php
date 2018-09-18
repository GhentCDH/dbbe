<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class MeterController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'meter_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Meter:';

    /**
     * @Route("/meters", name="meters_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/meters/edit", name="meters_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Meter:edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'meters_get' => $this->generateUrl('meters_get'),
                    'occurrence_deps_by_meter' => $this->generateUrl('occurrence_deps_by_meter', ['id' => 'meter_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'meter_post' => $this->generateUrl('meter_post'),
                    'meter_put' => $this->generateUrl('meter_put', ['id' => 'meter_id']),
                    'meter_delete' => $this->generateUrl('meter_delete', ['id' => 'meter_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'meters' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('meter_manager')->getAll()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/meters", name="meter_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/meters/{id}", name="meter_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/meters/{id}", name="meter_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
