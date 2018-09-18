<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class GenreController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'genre_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Genre:';

    /**
     * @Route("/genres", name="genres_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/genres/edit", name="genres_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Genre:edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'genres_get' => $this->generateUrl('genres_get'),
                    'occurrence_deps_by_genre' => $this->generateUrl('occurrence_deps_by_genre', ['id' => 'genre_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'genre_post' => $this->generateUrl('genre_post'),
                    'genre_put' => $this->generateUrl('genre_put', ['id' => 'genre_id']),
                    'genre_delete' => $this->generateUrl('genre_delete', ['id' => 'genre_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'genres' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('genre_manager')->getAll()
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/genres", name="genre_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/genres/{id}", name="genre_put")
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
     * @Route("/genres/{id}", name="genre_delete")
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