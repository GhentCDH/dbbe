<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\GenreManager;

class GenreController extends BaseController
{
    public function __construct(GenreManager $genreManager)
    {
        $this->manager = $genreManager;
        $this->templateFolder = 'Genre/';
    }

    /**
     * @Route("/genres", name="genres_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/genres/edit", name="genres_edit", methods={"GET"})
     * @return Response
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'Genre/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'genres_get' => $this->generateUrl('genres_get'),
                    'occurrence_deps_by_genre' => $this->generateUrl('occurrence_deps_by_genre', ['id' => 'genre_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_genre' => $this->generateUrl('type_deps_by_genre', ['id' => 'genre_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'genre_post' => $this->generateUrl('genre_post'),
                    'genre_put' => $this->generateUrl('genre_put', ['id' => 'genre_id']),
                    'genre_delete' => $this->generateUrl('genre_delete', ['id' => 'genre_id']),
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('app_login') : $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'genres' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @Route("/genres", name="genre_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/genres/{id}", name="genre_put", methods={"PUT"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/genres/{id}", name="genre_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
