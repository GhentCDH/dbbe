<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\BookClusterManager;
use App\Security\Roles;

class BookClusterController extends BaseController
{
    public function __construct(BookClusterManager $bookClusterManager)
    {
        $this->manager = $bookClusterManager;
        $this->templateFolder = 'BookCluster/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_clusters', name: 'book_clusters_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getAllJson('getTitle')
        );
    }

    /**
     * @return Response
     */
    #[Route(path: '/book_clusters/edit', name: 'book_clusters_edit', methods: ['GET'])]
    public function edit(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder  . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'book_clusters_get' => $this->generateUrl('book_clusters_get'),
                    'book_deps_by_book_cluster' => $this->generateUrl('book_deps_by_book_cluster', ['id' => 'book_cluster_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_cluster_post' => $this->generateUrl('book_cluster_post'),
                    'book_cluster_merge' => $this->generateUrl('book_cluster_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'book_cluster_put' => $this->generateUrl('book_cluster_put', ['id' => 'book_cluster_id']),
                    'book_cluster_delete' => $this->generateUrl('book_cluster_delete', ['id' => 'book_cluster_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    // @codingStandardsIgnoreEnd
                ]),
                'book_clusters' => json_encode($this->manager->getAllJson('getTitle')),
            ]
        );
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/book_clusters/{id}', name: 'book_cluster_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @param  int    $primaryId   first book cluster id (will stay)
     * @param  int    $secondaryId second book cluster id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_clusters/{primaryId}/{secondaryId}', name: 'book_cluster_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_clusters', name: 'book_cluster_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_clusters/{id}', name: 'book_cluster_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_clusters/{id}', name: 'book_cluster_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
