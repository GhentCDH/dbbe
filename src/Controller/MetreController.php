<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\MetreManager;
use App\Security\Roles;

class MetreController extends BaseController
{
    public function __construct(MetreManager $metreManager)
    {
        $this->manager = $metreManager;
        $this->templateFolder = 'Metre/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/metres', name: 'metres_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        return parent::getAll($request);
    }

    /**
     * @return Response
     */
    #[Route(path: '/metres/edit', name: 'metres_edit', methods: ['GET'])]
    public function edit(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Metre/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'metres_get' => $this->generateUrl('metres_get'),
                    'occurrence_deps_by_metre' => $this->generateUrl('occurrence_deps_by_metre', ['id' => 'metre_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_metre' => $this->generateUrl('type_deps_by_metre', ['id' => 'metre_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'metre_post' => $this->generateUrl('metre_post'),
                    'metre_put' => $this->generateUrl('metre_put', ['id' => 'metre_id']),
                    'metre_delete' => $this->generateUrl('metre_delete', ['id' => 'metre_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'metres' => json_encode(
                    $this->manager->getAllJson()
                ),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/metres', name: 'metre_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/metres/{id}', name: 'metre_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/metres/{id}', name: 'metre_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
