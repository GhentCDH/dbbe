<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AcknowledgementController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'acknowledgement_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Acknowledgement:';

    /**
     * @Route("/acknowledgements", name="acknowledgements_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/acknowledgements/edit", name="acknowledgements_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Acknowledgement:edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'acknowledgements_get' => $this->generateUrl('acknowledgements_get'),
                    'occurrence_deps_by_acknowledgement' => $this->generateUrl('occurrence_deps_by_acknowledgement', ['id' => 'acknowledgement_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_acknowledgement' => $this->generateUrl('type_deps_by_acknowledgement', ['id' => 'acknowledgement_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'acknowledgement_post' => $this->generateUrl('acknowledgement_post'),
                    'acknowledgement_put' => $this->generateUrl('acknowledgement_put', ['id' => 'acknowledgement_id']),
                    'acknowledgement_delete' => $this->generateUrl('acknowledgement_delete', ['id' => 'acknowledgement_id']),
                    'login' => $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'acknowledgements' => json_encode(
                    $this->get('acknowledgement_manager')->getAllJson()
                ),
            ]
        );
    }

    /**
     * @Route("/acknowledgements", name="acknowledgement_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/acknowledgements/{id}", name="acknowledgement_put")
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
     * @Route("/acknowledgements/{id}", name="acknowledgement_delete")
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
