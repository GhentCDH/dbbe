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
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/acknowledgements', name: 'acknowledgements_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        return parent::getAll($request);
    }

    /**
     * @return Response
     */
    #[Route(path: '/acknowledgements/edit', name: 'acknowledgements_edit', methods: ['GET'])]
    public function edit(): Response
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
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'acknowledgements' => json_encode(
                    $this->manager->getAllJson()
                ),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/acknowledgements', name: 'acknowledgement_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/acknowledgements/{id}', name: 'acknowledgement_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/acknowledgements/{id}', name: 'acknowledgement_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
