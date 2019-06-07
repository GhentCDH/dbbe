<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SelfDesignationController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'self_designation_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:SelfDesignation:';

    /**
     * @Route("/self-designations", name="self_designations_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/self-designations/edit", name="self_designations_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:SelfDesignation:edit.html.twig',
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
                    'login' => $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'selfDesignations' => json_encode(
                    $this->get('self_designation_manager')->getAllJson()
                ),
            ]
        );
    }

    /**
     * @Route("/self-designations/{primaryId}/{secondaryId}", name="self_designation_merge")
     * @Method("PUT")
     * @param  int    $primaryId   first self designation id (will stay)
     * @param  int    $secondaryId second self designation id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/self-designations", name="self_designation_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/self-designations/{id}", name="self_designation_put")
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
     * @Route("/self-designations/{id}", name="self_designation_delete")
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
