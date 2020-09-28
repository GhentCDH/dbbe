<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\ObjectStorage\BookSeriesManager;

class BookSeriesController extends BaseController
{
    public function __construct(BookSeriesManager $bookSeriesManager)
    {
        $this->manager = $bookSeriesManager;
        $this->templateFolder = '@App/BookSeries/';
    }

    /**
     * @Route("/book_seriess", name="book_seriess_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getAllMiniShortJson()
        );
    }

    /**
     * @Route("/book_seriess/edit", name="book_seriess_edit")
     * @Method("GET")
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

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
                    'login' => $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'book_seriess' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @Route("/book_series/{id}", name="book_series_get")
     * @Method("GET")
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @Route("/book_seriess/{primaryId}/{secondaryId}", name="book_series_merge")
     * @Method("PUT")
     * @param  int    $primaryId   first book series id (will stay)
     * @param  int    $secondaryId second book series id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/book_seriess", name="book_series_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/book_seriess/{id}", name="book_series_put")
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
     * @Route("/book_seriess/{id}", name="book_series_delete")
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
