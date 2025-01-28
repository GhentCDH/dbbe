<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\BookManager;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\RoleManager;
use App\Security\Roles;

class BookController extends BaseController
{
    public function __construct(BookManager $bookManager)
    {
        $this->manager = $bookManager;
        $this->templateFolder = 'Book/';
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    #[Route(path: '/books', name: 'books_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse|RedirectResponse
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMini($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    #[Route(path: '/books/add', name: 'book_add', methods: ['GET'])]
    public function add(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        $args = func_get_args();
        $args[] = null;

        return call_user_func_array([$this, 'edit'], $args);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/books/{id}', name: 'book_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all books that have a dependency on a book cluster
     * @param  int    $id book cluster id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/book_clusters/{id}', name: 'book_deps_by_book_cluster', methods: ['GET'])]
    public function getDepsByBookCluster(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookClusterDependencies');
    }

    /**
     * Get all books that have a dependency on a book series
     * @param  int    $id book series id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/book_seriess/{id}', name: 'book_deps_by_book_series', methods: ['GET'])]
    public function getDepsByBookSeries(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookSeriesDependencies');
    }

    /**
     * Get all books that have a dependency on a person
     * (bibrole)
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/persons/{id}', name: 'book_deps_by_person', methods: ['GET'])]
    public function getDepsByPerson(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all books that have a dependency on a role
     * (bibrole)
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/roles/{id}', name: 'book_deps_by_role', methods: ['GET'])]
    public function getDepsByRole(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all books that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/managements/{id}', name: 'book_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books', name: 'book_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Book added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/{id}', name: 'book_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Book data successfully saved.');
        }

        return $response;
    }

    /**
     * @param  int    $primaryId   first book id (will stay)
     * @param  int    $secondaryId second book id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/{primaryId}/{secondaryId}', name: 'book_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/books/{id}', name: 'book_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }

    /**
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    #[Route(path: '/books/{id}/edit', name: 'book_edit', methods: ['GET'])]
    public function edit(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

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
                    'login' => $this->generateUrl('login'),
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
