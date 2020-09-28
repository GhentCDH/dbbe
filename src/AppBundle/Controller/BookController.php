<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\ObjectStorage\BookManager;
use AppBundle\ObjectStorage\IdentifierManager;
use AppBundle\ObjectStorage\ManagementManager;
use AppBundle\ObjectStorage\RoleManager;

class BookController extends BaseController
{
    public function __construct(BookManager $bookManager)
    {
        $this->manager = $bookManager;
        $this->templateFolder = '@App/Book/';
    }

    /**
     * @Route("/books", name="books_get")
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
     * @Route("/books/add", name="book_add")
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
     * @Route("/books/{id}", name="book_get")
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
     * Get all books that have a dependency on a book cluster
     * @Route("/books/book_clusters/{id}", name="book_deps_by_book_cluster")
     * @Method("GET")
     * @param  int    $id book cluster id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBookCluster(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookClusterDependencies');
    }

    /**
     * Get all books that have a dependency on a book series
     * @Route("/books/book_seriess/{id}", name="book_deps_by_book_series")
     * @Method("GET")
     * @param  int    $id book series id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBookSeries(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookSeriesDependencies');
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
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
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
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
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
     * @Route("/books/{primaryId}/{secondaryId}", name="book_merge")
     * @Method("PUT")
     * @param  int    $primaryId   first book id (will stay)
     * @param  int    $secondaryId second book id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
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
                'id' => $id,
                'urls' => json_encode([
                    'book_get' => $this->generateUrl('book_get', ['id' => $id == null ? 'book_id' : $id]),
                    'book_post' => $this->generateUrl('book_post'),
                    'book_put' => $this->generateUrl('book_put', ['id' => $id == null ? 'book_id' : $id]),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'book_clusters_get' => $this->generateUrl('book_clusters_get'),
                    'book_clusters_edit' => $this->generateUrl('book_clusters_edit'),
                    'book_seriess_get' => $this->generateUrl('book_seriess_get'),
                    'book_seriess_edit' => $this->generateUrl('book_seriess_edit'),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('saml_login'),
                ]),
                'data' => json_encode([
                    'book' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode($identifierManager->getByTypeJson('book')),
                'roles' => json_encode($roleManager->getByTypeJson('book')),
            ]
        );
    }
}
