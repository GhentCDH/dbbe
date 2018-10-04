<?php

namespace AppBundle\Controller;

use AppBundle\Utils\ArrayToJson;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class BookChapterController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'book_chapter_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:BookChapter:';

    /**
     * @Route("/bookchapters/add", name="book_chapter_add")
     * @Method("GET")
     * @param Request $request
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/bookchapters/{id}", name="book_chapter_get")
     * @Method("GET")
      * @param int     $id
      * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all book chapters that have a dependency on a book
     * (document_contains)
     * @Route("/bookchapters/books/{id}", name="book_chapter_deps_by_book")
     * @Method("GET")
     * @param  int    $id book id
     * @param Request $request
     */
    public function getDepsByBook(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all book chapters that have a dependency on a person
     * (bibrole)
     * @Route("/bookchapters/persons/{id}", name="book_chapter_deps_by_person")
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
     * Get all book chapters that have a dependency on a role
     * (bibrole)
     * @Route("/bookchapters/roles/{id}", name="book_chapter_deps_by_role")
     * @Method("GET")
     * @param  int    $id role id
     * @param Request $request
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * @Route("/bookchapters", name="book_chapter_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Book chapter added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/bookchapters/{id}", name="book_chapter_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Book chapter data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/bookchapters/{id}", name="book_chapter_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/bookchapters/{id}/edit", name="book_chapter_edit")
     * @Method("GET")
     * @param  int|null $id
     * @param Request $request
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'book_chapter_get' => $this->generateUrl('book_chapter_get', ['id' => $id == null ? 'book_chapter_id' : $id]),
                    'book_chapter_post' => $this->generateUrl('book_chapter_post'),
                    'book_chapter_put' => $this->generateUrl('book_chapter_put', ['id' => $id == null ? 'book_chapter_id' : $id]),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'data' => json_encode([
                    'bookChapter' => empty($id)
                        ? null
                        : $this->get(self::MANAGER)->getFull($id)->getJson(),
                    'modernPersons' => ArrayToJson::arrayToShortJson(
                        $this->get('person_manager')->getAllModernPersons()
                    ),
                    'books' => ArrayToJson::arrayToShortJson(
                        $this->get('book_manager')->getAllMini()
                    ),
                    'managements' => ArrayToJson::arrayToShortJson($this->get('management_manager')->getAll()),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('identifier_manager')->getIdentifiersByType('bookChapter')
                    )
                ),
                'roles' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('role_manager')->getRolesByType('bookChapter')
                    )
                ),
            ]
        );
    }
}
