<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BookController extends BaseController
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
     * @Route("/books", name="books_base")
     * @Method("GET")
     * @param Request $request
     */
    public function base(Request $request)
    {
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

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
     * Get all books that have a dependency on a person
     * (bibrole)
     * @Route("/books/persons/{id}", name="book_deps_by_person")
     * @Method("GET")
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all books that have a dependency on a role
     * (bibrole)
     * @Route("/books/roles/{id}", name="book_deps_by_role")
     * @Method("GET")
     * @param  int    $id role id
     * @param Request $request
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all books that have a dependency on a management collection
     * (reference)
     * @Route("/books/managements/{id}", name="book_deps_by_management")
     * @Method("GET")
     * @param  int    $id management id
     * @param Request $request
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
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
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'book' => empty($id)
                        ? null
                        : $this->get('book_manager')->getFull($id)->getJson(),
                    'modernPersons' => $this->get('person_manager')->getAllModernShortJson(),
                    'managements' => $this->get('management_manager')->getAllShortJson(),
                ]),
                'identifiers' => json_encode($this->get('identifier_manager')->getByTypeJson('book')),
                'roles' => json_encode($this->get('role_manager')->getByTypeJson('book')),
            ]
        );
    }
}
