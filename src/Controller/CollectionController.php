<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\CollectionManager;

class CollectionController extends BaseController
{
    public function __construct(CollectionManager $collectionManager)
    {
        $this->manager = $collectionManager;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/collections', name: 'collection_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Collection added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id collection id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/collections/{id}', name: 'collection_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Collection successfully saved.');
        }

        return $response;
    }

    /**
     * @param int $id collection id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/collections/{id}', name: 'collection_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
