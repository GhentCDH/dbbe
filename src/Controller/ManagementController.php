<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\ManagementManager;

class ManagementController extends BaseController
{
    public function __construct(ManagementManager $managementManager)
    {
        $this->manager = $managementManager;
        $this->templateFolder = 'Management/';
    }

    /**
     * @Route("/managements", name="managements_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        return parent::getAll($request);
    }

    /**
     * @Route("/managements/edit", name="managements_edit", methods={"GET"})
     * @return Response
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            $this->templateFolder  . 'edit.html.twig',
            [
                'urls' => json_encode([
                    'managements_get' => $this->generateUrl('managements_get'),
                    'management_post' => $this->generateUrl('management_post'),
                    'management_put' => $this->generateUrl('management_put', ['id' => 'management_id']),
                    'management_delete' => $this->generateUrl('management_delete', ['id' => 'management_id']),
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('app_login') : $this->generateUrl('saml_login'),
                ]),
                'managements' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @Route("/managements", name="management_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/managements/{id}", name="management_put", methods={"PUT"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/managements/{id}", name="management_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
