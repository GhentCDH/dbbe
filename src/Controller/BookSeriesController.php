<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\BookSeriesManager;
use App\Security\Roles;

class BookSeriesController extends BaseController
{
    public function __construct(BookSeriesManager $bookSeriesManager)
    {
        $this->manager = $bookSeriesManager;
        $this->templateFolder = 'BookSeries/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_seriess', name: 'book_seriess_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getAllMiniShortJson()
        );
    }

    #[Route(path: '/book_seriess/edit', name: 'book_seriess_edit', methods: ['GET'])]
    public function edit()
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder  . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'book_seriess_get' => $this->generateUrl('book_seriess_get'),
                    'book_deps_by_book_series' => $this->generateUrl('book_deps_by_book_series', ['id' => 'book_series_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_series_post' => $this->generateUrl('book_series_post'),
                    'book_series_merge' => $this->generateUrl('book_series_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'book_series_put' => $this->generateUrl('book_series_put', ['id' => 'book_series_id']),
                    'book_series_delete' => $this->generateUrl('book_series_delete', ['id' => 'book_series_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'book_seriess' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/book_series/{id}', name: 'book_series_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @param  int    $primaryId   first book series id (will stay)
     * @param  int    $secondaryId second book series id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_seriess/{primaryId}/{secondaryId}', name: 'book_series_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_seriess', name: 'book_series_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_seriess/{id}', name: 'book_series_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/book_seriess/{id}', name: 'book_series_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
