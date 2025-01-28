<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\GenreManager;
use App\Security\Roles;

class GenreController extends BaseController
{
    public function __construct(GenreManager $genreManager)
    {
        $this->manager = $genreManager;
        $this->templateFolder = 'Genre/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/genres', name: 'genres_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        return parent::getAll($request);
    }

    /**
     * @return Response
     */
    #[Route(path: '/genres/edit', name: 'genres_edit', methods: ['GET'])]
    public function edit(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

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
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'genres' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/genres', name: 'genre_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/genres/{id}', name: 'genre_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/genres/{id}', name: 'genre_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
