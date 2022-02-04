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
     * @Route("/collections", name="collection_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Collection added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/collections/{id}", name="collection_put", methods={"PUT"})
     * @param  int    $id collection id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Collection successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/collections/{id}", name="collection_delete", methods={"DELETE"})
     * @param int $id collection id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
