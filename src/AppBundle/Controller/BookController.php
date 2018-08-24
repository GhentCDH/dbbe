<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\Utils\ArrayToJson;

class BookController extends BasicController
{
    /**
     * @var string
     */
    const MANAGER = 'book_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Book:';

    /**
     * @Route("/books/add", name="book_add")
     * @Method("GET")
     * @param Request $request
     */
    public function add(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->edit(null, $request);
    }

    /**
     * @Route("/books/{id}", name="book_get")
     * @Method("GET")
     * @param int     $id
     * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @Route("/books", name="book_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Book added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/books/{id}", name="book_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Book data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/books/{id}", name="book_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return Response
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/books/{id}/edit", name="book_edit")
     * @Method("GET")
     * @param  int|null $id
     * @param Request $request
     * @return Response
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    'book_get' => $this->generateUrl('book_get', ['id' => $id == null ? 'book_id' : $id]),
                    'book_post' => $this->generateUrl('book_post'),
                    'book_put' => $this->generateUrl('book_put', ['id' => $id == null ? 'book_id' : $id]),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'book' => empty($id)
                        ? null
                        : $this->get('book_manager')->getFull($id)->getJson(),
                    'modernPersons' => ArrayToJson::arrayToShortJson(
                        $this->get('person_manager')->getAllModernPersons()
                    ),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('identifier_manager')->getIdentifiersByType('book')
                    )
                ),
                'roles' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('role_manager')->getRolesByType('book')
                    )
                ),
            ]
        );
    }
}
