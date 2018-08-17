<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class BookController extends Controller
{
    /**
     * @Route("/books/add", name="book_add")
     * @Method("GET")
     * @param Request $request
     */
    public function addBook(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->editBook(null, $request);
    }

    /**
     * @Route("/books/{id}", name="book_get")
     * @Method("GET")
     * @param int     $id
     * @param Request $request
     */
    public function getBook(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            try {
                $book = $this->get('book_manager')->getFull($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($book->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $book = $this->get('book_manager')->getFull($id);
            return $this->render(
                'AppBundle:Book:detail.html.twig',
                ['book' => $book]
            );
        }
    }

    /**
     * @Route("/books", name="book_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postBook(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $book = $this
                    ->get('book_manager')
                    ->add(json_decode($request->getContent()));
            } catch (BadRequestHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->addFlash('success', 'Book added successfully.');

            return new JsonResponse($book->getJson());
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/books/{id}", name="book_put")
     * @Method("PUT")
     * @param  int    $id book id
     * @param Request $request
     * @return JsonResponse
     */
    public function putBook(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $book = $this
                    ->get('book_manager')
                    ->update($id, json_decode($request->getContent()));
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            } catch (BadRequestHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->addFlash('success', 'Book data successfully saved.');

            return new JsonResponse($book->getJson());
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/books/{id}", name="book_delete")
     * @Method("DELETE")
     * @param  int    $id book id
     * @param Request $request
     * @return Response
     */
    public function deleteBook(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * @Route("/books/{id}/edit", name="book_edit")
     * @Method("GET")
     * @param  int|null $id book id
     * @param Request $request
     * @return Response
     */
    public function editBook(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Book:edit.html.twig',
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
                    'modernPersons' => ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllModernPersons()),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getIdentifiersByType('book'))
                ),
                'roles' => json_encode(
                    ArrayToJson::arrayToJson($this->get('role_manager')->getRolesByType('book'))
                ),
            ]
        );
    }
}
