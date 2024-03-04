<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\AcknowledgementManager;
use App\Security\Roles;

class AcknowledgementController extends BaseController
{
    public function __construct(AcknowledgementManager $acknowledgementManager)
    {
        $this->manager = $acknowledgementManager;
        $this->templateFolder = 'Acknowledgement/';
    }

    /**
     * @Route("/acknowledgements", name="acknowledgements_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/acknowledgements/edit", name="acknowledgements_edit", methods={"GET"})
     * @return Response
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Acknowledgement/edit.html.twig',
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
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('idci_keycloak_security_auth_connect') : $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'acknowledgements' => json_encode(
                    $this->manager->getAllJson()
                ),
            ]
        );
    }

    /**
     * @Route("/acknowledgements", name="acknowledgement_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/acknowledgements/{id}", name="acknowledgement_put", methods={"PUT"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/acknowledgements/{id}", name="acknowledgement_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
