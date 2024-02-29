<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\BookClusterManager;

class BookClusterController extends BaseController
{
    public function __construct(BookClusterManager $bookClusterManager)
    {
        $this->manager = $bookClusterManager;
        $this->templateFolder = 'BookCluster/';
    }

    /**
     * @Route("/book_clusters", name="book_clusters_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getAllJson('getTitle')
        );
    }

    /**
     * @Route("/book_clusters/edit", name="book_clusters_edit", methods={"GET"})
     * @return Response
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

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
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('idci_keycloak_security_auth_connect') : $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'book_clusters' => json_encode($this->manager->getAllJson('getTitle')),
            ]
        );
    }

    /**
     * @Route("/book_clusters/{id}", name="book_cluster_get", methods={"GET"})
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @Route("/book_clusters/{primaryId}/{secondaryId}", name="book_cluster_merge", methods={"PUT"})
     * @param  int    $primaryId   first book cluster id (will stay)
     * @param  int    $secondaryId second book cluster id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/book_clusters", name="book_cluster_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/book_clusters/{id}", name="book_cluster_put", methods={"PUT"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/book_clusters/{id}", name="book_cluster_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
