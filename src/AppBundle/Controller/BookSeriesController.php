<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookSeriesController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'book_series_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:BookSeries:';

    /**
     * @Route("/book_seriess", name="book_seriess_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->get(static::MANAGER)->getAllMiniShortJson()
        );
    }

    /**
     * @Route("/book_seriess/edit", name="book_seriess_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER  . 'edit.html.twig',
            [
                'urls' => json_encode([
                    'book_seriess_get' => $this->generateUrl('book_seriess_get'),
                    'book_deps_by_book_series' => $this->generateUrl('book_deps_by_book_series', ['id' => 'book_series_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_series_post' => $this->generateUrl('book_series_post'),
                    'book_series_merge' => $this->generateUrl('book_series_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'book_series_put' => $this->generateUrl('book_series_put', ['id' => 'book_series_id']),
                    'book_series_delete' => $this->generateUrl('book_series_delete', ['id' => 'book_series_id']),
                    'login' => $this->generateUrl('saml_login'),
                ]),
                'book_seriess' => json_encode($this->get(self::MANAGER)->getAllMiniShortJson()),
            ]
        );
    }

    /**
     * @Route("/book_series/{id}", name="book_series_get")
     * @Method("GET")
     * @param int     $id
     * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            try {
                $object = $this->get(static::MANAGER)->getFull($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($object->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $object = $this->get(static::MANAGER)->getFull($id);
            return $this->render(
                static::TEMPLATE_FOLDER . 'detail.html.twig',
                [
                    $object::CACHENAME => $object,
                    'books' => $this->get(static::MANAGER)->getBooks($id),
                ]
            );
        }
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
