<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\ObjectStorage\BookChapterManager;
use AppBundle\ObjectStorage\IdentifierManager;
use AppBundle\ObjectStorage\ManagementManager;
use AppBundle\ObjectStorage\RoleManager;

class BookChapterController extends BaseController
{
    public function __construct(BookChapterManager $bookChapterManager)
    {
        $this->manager = $bookChapterManager;
        $this->templateFolder = '@App/BookChapter/';
    }

    /**
     * @Route("/bookchapters", name="book_chapters_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function getAll(Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMini($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/bookchapters/add", name="book_chapter_add")
     * @Method("GET")
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    public function add(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        $args = func_get_args();
        $args[] = null;

        return call_user_func_array([$this, 'edit'], $args);
    }

    /**
     * @Route("/bookchapters/{id}", name="book_chapter_get")
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
     * Get all book chapters that have a dependency on a book
     * (document_contains)
     * @Route("/bookchapters/books/{id}", name="book_chapter_deps_by_book")
     * @Method("GET")
     * @param int $id book id
     * @param Request $request
     * @return JsonResponse
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
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all book chapters that have a dependency on a management collection
     * (reference)
     * @Route("/bookchapters/managements/{id}", name="book_chapter_deps_by_management")
     * @Method("GET")
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
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
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    public function edit(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'urls' => json_encode([
                    'book_chapter_get' => $this->generateUrl('book_chapter_get', ['id' => $id == null ? 'book_chapter_id' : $id]),
                    'book_chapter_post' => $this->generateUrl('book_chapter_post'),
                    'book_chapter_put' => $this->generateUrl('book_chapter_put', ['id' => $id == null ? 'book_chapter_id' : $id]),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'books_get' => $this->generateUrl('books_get'),
                    'bibliographies_search' => $this->generateUrl('bibliographies_search'),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('saml_login'),
                ]),
                'data' => json_encode([
                    'bookChapter' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode($identifierManager->getByTypeJson('bookChapter')),
                'roles' => json_encode($roleManager->getByTypeJson('bookChapter')),
                // @codingStandardsIgnoreEnd
            ]
        );
    }
}
