<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ElasticSearchService\ElasticManuscriptService;
use App\Model\Status;
use App\ObjectStorage\AcknowledgementManager;
use App\ObjectStorage\ContentManager;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\ManuscriptManager;
use App\ObjectStorage\OriginManager;
use App\ObjectStorage\PersonManager;
use App\ObjectStorage\RoleManager;
use App\ObjectStorage\StatusManager;

use App\Security\Roles;

class ManuscriptController extends BaseController
{
    public function __construct(ManuscriptManager $manuscriptManager)
    {
        $this->manager = $manuscriptManager;
        $this->templateFolder = 'Manuscript/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts', name: 'manuscripts_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMini($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('manuscripts_search', ['request' =>  $request], 301);
    }

    /**
     * @param Request $request
     * @param ElasticManuscriptService $elasticManuscriptService
     * @param IdentifierManager $identifierManager
     * @param ManagementManager $managementManager
     * @return Response
     */
    #[Route(path: '/manuscripts/search', name: 'manuscripts_search', methods: ['GET'])]
    public function search(
        Request $request,
        ElasticManuscriptService $elasticManuscriptService,
        IdentifierManager $identifierManager,
        ManagementManager $managementManager
    ) {
        return $this->render(
            'Manuscript/overview.html.twig',
            // @codingStandardsIgnoreStart Generic.Files.LineLength
            [
                'urls' => json_encode([
                    'manuscripts_search_api' => $this->generateUrl('manuscripts_search_api'),
                    'occurrence_deps_by_manuscript' => $this->generateUrl('occurrence_deps_by_manuscript', ['id' => 'manuscript_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'manuscript_edit' => $this->generateUrl('manuscript_edit', ['id' => 'manuscript_id']),
                    'manuscript_delete' => $this->generateUrl('manuscript_delete', ['id' => 'manuscript_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    'managements_add' => $this->generateUrl('manuscripts_managements_add'),
                    'managements_remove' => $this->generateUrl('manuscripts_managements_remove'),
                ]),
                'data' => json_encode(
                    $elasticManuscriptService->searchAndAggregate(
                        $this->sanitize($request->query->all(), $identifierManager),
                        $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
                    )
                ),
                'identifiers' => json_encode(
                    $identifierManager->getPrimaryByTypeJson('manuscript')
                ),
                'managements' => json_encode(
                    $this->isGranted(Roles::ROLE_EDITOR_VIEW) ? $managementManager->getAllShortJson() : []
                ),
            ]
            // @codingStandardsIgnoreEnd
        );
    }

    /**
     * @param Request $request
     * @param ElasticManuscriptService $elasticManuscriptService
     * @param IdentifierManager $identifierManager
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/search_api', name: 'manuscripts_search_api', methods: ['GET'])]
    public function searchAPI(
        Request $request,
        ElasticManuscriptService $elasticManuscriptService,
        IdentifierManager $identifierManager
    ) {
        $this->throwErrorIfNotJson($request);
        $result = $elasticManuscriptService->searchAndAggregate(
            $this->sanitize($request->query->all(), $identifierManager),
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );

        return new JsonResponse($result);
    }

    /**
     * @param ContentManager $contentManager
     * @param PersonManager $personManager
     * @param OriginManager $originManager
     * @param AcknowledgementManager $acknowledgementManager
     * @param StatusManager $statusManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    #[Route(path: '/manuscripts/add', name: 'manuscript_add', methods: ['GET'])]
    public function add(
        ContentManager $contentManager,
        PersonManager $personManager,
        OriginManager $originManager,
        AcknowledgementManager $acknowledgementManager,
        StatusManager $statusManager,
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
     * @param int $id manuscript id
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/manuscripts/{id}', name: 'manuscript_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    #[Route(path: '/manuscript/view/id/{id}', name: 'manuscript_get_old', methods: ['GET'])]
    public function getOld(int $id): RedirectResponse
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('manuscript_get', ['id' => $newId], 301);
    }

    /**
     * Get all manuscripts that have a dependency on a region
     * (located_at / factoid)
     * @param int $id region id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/regions/{id}', name: 'manuscript_deps_by_region', methods: ['GET'])]
    public function getDepsByRegion(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on an institution
     * (located_at / factoid)
     * @param int $id institution id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/institutions/{id}', name: 'manuscript_deps_by_institution', methods: ['GET'])]
    public function getDepsByInstitution(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getInstitutionDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a collection
     * (located_at / factoid)
     * @param int $id collection id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/collections/{id}', name: 'manuscript_deps_by_collection', methods: ['GET'])]
    public function getDepsByCollection(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getCollectionDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a content
     * (document_genre)
     * @param int $id content id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/contents/{id}', name: 'manuscript_deps_by_content', methods: ['GET'])]
    public function getDepsByContent(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getContentDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a status
     * (document_status)
     * @param int $id status id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/statuses/{id}', name: 'manuscript_deps_by_status', methods: ['GET'])]
    public function getDepsByStatus(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getStatusDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a person
     * (bibrole, factoid)
     * @param int $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/persons/{id}', name: 'manuscript_deps_by_person', methods: ['GET'])]
    public function getDepsByPerson(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a role
     * (bibrole)
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/roles/{id}', name: 'manuscript_deps_by_role', methods: ['GET'])]
    public function getDepsByRole(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on an article
     * (reference)
     * @param int $id article id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/articles/{id}', name: 'manuscript_deps_by_article', methods: ['GET'])]
    public function getDepsByArticle(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a blog post
     * (reference)
     * @param int $id blog post id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/blogposts/{id}', name: 'manuscript_deps_by_blog_post', methods: ['GET'])]
    public function getDepsByBlogPost(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBlogPostDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a book
     * (reference)
     * @param int $id book id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/books/{id}', name: 'manuscript_deps_by_book', methods: ['GET'])]
    public function getDepsByBook(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a book chapter
     * (reference)
     * @param int $id book chapter id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/bookchapters/{id}', name: 'manuscript_deps_by_book_chapter', methods: ['GET'])]
    public function getDepsByBookChapter(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on an online source
     * (reference)
     * @param int $id online source id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/onlinesources/{id}', name: 'manuscript_deps_by_online_source', methods: ['GET'])]
    public function getDepsByOnlineSource(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a PhD thesis
     * (reference)
     * @param int $id phd id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/phd_theses/{id}', name: 'manuscript_deps_by_phd', methods: ['GET'])]
    public function getDepsByPhd(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPhdDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a bib varia
     * (reference)
     * @param int $id bib varia id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/bib_varia/{id}', name: 'manuscript_deps_by_bib_varia', methods: ['GET'])]
    public function getDepsByBibVaria(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBibVariaDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/managements/{id}', name: 'manuscript_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts', name: 'manuscript_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Manuscript added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id manuscript id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/{id}', name: 'manuscript_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Manuscript data successfully saved.');
        }

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/managements/add', name: 'manuscripts_managements_add', methods: ['PUT'])]
    public function addManagements(Request $request): JsonResponse
    {
        return parent::addManagements($request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/manuscripts/managements/remove', name: 'manuscripts_managements_remove', methods: ['PUT'])]
    public function removeManagements(Request $request): JsonResponse
    {
        return parent::removeManagements($request);
    }

    /**
     * @param  int    $id manuscript id
     * @param Request $request
     * @return Response
     */
    #[Route(path: '/manuscripts/{id}', name: 'manuscript_delete', methods: ['DELETE'])]
    public function deleteManuscript(int $id, Request $request): Response
    {
        return parent::delete($id, $request);
    }

    /**
     * @param ContentManager $contentManager
     * @param PersonManager $personManager
     * @param OriginManager $originManager
     * @param AcknowledgementManager $acknowledgementManager
     * @param StatusManager $statusManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id manuscript id
     * @return Response
     */
    #[Route(path: '/manuscripts/{id}/edit', name: 'manuscript_edit', methods: ['GET'])]
    public function edit(
        ContentManager $contentManager,
        PersonManager $personManager,
        OriginManager $originManager,
        AcknowledgementManager $acknowledgementManager,
        StatusManager $statusManager,
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Manuscript/edit.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'urls' => json_encode([
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => $id == null ? 'manuscript_id' : $id]),
                    'manuscript_post' => $this->generateUrl('manuscript_post'),
                    'manuscript_put' => $this->generateUrl('manuscript_put', ['id' => $id == null ? 'manuscript_id' : $id]),
                    'locations_get' => $this->generateUrl('locations_get', ['type' => 'manuscript']),
                    'locations_edit' => $this->generateUrl('locations_edit'),
                    'contents_get' => $this->generateUrl('contents_get'),
                    'contents_edit' => $this->generateUrl('contents_edit'),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'historical_persons_get' => $this->generateUrl('persons_get', ['type' => 'historical']),
                    'origins_get' => $this->generateUrl('origins_get', ['type' => 'manuscript']),
                    'origins_edit' => $this->generateUrl('origins_edit'),
                    'articles_get' => $this->generateUrl('articles_get'),
                    'blog_posts_get' => $this->generateUrl('blog_posts_get'),
                    'books_get' => $this->generateUrl('books_get'),
                    'book_chapters_get' => $this->generateUrl('book_chapters_get'),
                    'online_sources_get' => $this->generateUrl('online_sources_get'),
                    'phds_get' => $this->generateUrl('phds_get'),
                    'bib_varias_get' => $this->generateUrl('bib_varias_get'),
                    'bibliographies_search' => $this->generateUrl('bibliographies_search'),
                    'acknowledgements_get' => $this->generateUrl('acknowledgements_get'),
                    'acknowledgements_edit' => $this->generateUrl('acknowledgements_edit'),
                    'statuses_get' => $this->generateUrl('statuses_get', ['type' => 'manuscript']),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'dbbe_persons_get' => $this->generateUrl('persons_get', ['type' => 'dbbe']),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                ]),
                'data' => json_encode([
                    'manuscript' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'contents' => $contentManager->getAllShortJson(),
                    'dbbePersons' => $personManager->getAllDBBEShortJson(),
                    'origins' => $originManager->getByTypeShortJson('manuscript'),
                    'acknowledgements' => $acknowledgementManager->getAllShortJson(),
                    'statuses' => $statusManager->getByTypeShortJson(Status::MANUSCRIPT),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $identifierManager->getByTypeJson('manuscript')
                ),
                'roles' => json_encode(
                    $roleManager->getByTypeJson('manuscript')
                ),
                'contributorRoles' => json_encode(
                    $roleManager->getContributorByTypeJson('manuscript')
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * Sanitize data from request string
     * @param array $params
     * @param IdentifierManager $identifierManager
     * @return array
     */
    private function sanitize(
        array $params,
        IdentifierManager $identifierManager
    ): array {
        $defaults = [
            'limit' => 25,
            'page' => 1,
            'ascending' => 1,
            'orderBy' => ['name.keyword'],
        ];
        $esParams = [];

        // Pagination
        if (isset($params['limit']) && is_numeric($params['limit'])) {
            $esParams['limit'] = $params['limit'];
        } else {
            $esParams['limit'] = $defaults['limit'];
        }
        if (isset($params['page']) && is_numeric($params['page'])) {
            $esParams['page'] = $params['page'];
        } else {
            $esParams['page'] = $defaults['page'];
        }


        // Sorting
        if (isset($params['orderBy'])) {
            if (isset($params['ascending']) && ($params['ascending'] == '0' || $params['ascending'] == '1')) {
                $esParams['ascending'] = intval($params['ascending']);
            } else {
                $esParams['ascending'] = $defaults['ascending'];
            }
            if (($params['orderBy']) == 'name') {
                $esParams['orderBy'] = ['name.keyword'];
            } elseif (($params['orderBy']) == 'date') {
                // when sorting in descending order => sort by ceiling, else: sort by floor
                if (isset($params['ascending']) && $params['ascending'] == 0) {
                    $esParams['orderBy'] = ['date_ceiling_year', 'date_floor_year'];
                } else {
                    $esParams['orderBy'] = ['date_floor_year', 'date_ceiling_year'];
                }
            } elseif (($params['orderBy']) == 'occ') {
                $esParams['orderBy'] = ['number_of_occurrences'];
            } elseif (($params['orderBy']) == 'created') {
                $esParams['orderBy'] = ['created'];
            } elseif (($params['orderBy']) == 'modified') {
                $esParams['orderBy'] = ['modified'];
            } else {
                $esParams['orderBy'] = $defaults['orderBy'];
            }
            // Don't set default order if there is a text field filter
        } elseif (!(isset($params['filters']['comment']))) {
            $esParams['orderBy'] = $defaults['orderBy'];
        }

        // Filtering
        $filters = [];
        if (isset($params['filters']) && is_array($params['filters'])) {
            $identifiers = array_keys($identifierManager->getPrimaryByType('manuscript'));

            foreach (array_keys($params['filters']) as $key) {
                switch ($key) {
                    case 'content':
                    case 'person':
                    case 'role':
                    case 'origin':
                    case 'acknowledgement':
                        if (is_array($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'content_op':
                    case 'origin_op':
                    case 'acknowledgement_op':
                    case 'shelf':
                    case 'comment':
                    case 'date_search_type':
                        if (is_string($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'city':
                    case 'library':
                    case 'collection':
                    case 'public':
                    case 'management':
                        if (is_numeric($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'management_inverse':
                        if (
                            is_string($params['filters'][$key])
                            && ($params['filters'][$key] == 'true'
                                || $params['filters'][$key] == 'false'
                            )
                        ) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'date':
                        if (is_array($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                            foreach (array_keys($params['filters'][$key]) as $subKey) {
                                switch ($subKey) {
                                    case 'year_from':
                                    case 'year_to':
                                        if (is_numeric($params['filters'][$key][$subKey])) {
                                            $filters[$key][$subKey] = $params['filters'][$key][$subKey];
                                        }
                                        break;
                                }
                            }
                        }
                        break;
                }

                if (str_ends_with($key, '_available') && in_array(substr($key, 0, -10), $identifiers)) {
                    if (is_string($params['filters'][$key]) && in_array($params['filters'][$key], ['0', '1'])) {
                        $filters[$key] = $params['filters'][$key];
                    }
                }
                if (in_array($key, $identifiers)) {
                    if (is_string($params['filters'][$key])) {
                        $filters[$key] = $params['filters'][$key];
                    }
                }
            }
        }

        // limit results to public if no access rights
        if (!$this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
            $filters['public'] = '1';
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        if (!empty($filters)) {
            // sanitize date search type
            if (!(isset($filters['date_search_type'])
                && in_array($filters['date_search_type'], ['exact', 'included', 'include', 'overlap']))) {
                $filters['date_search_type'] = 'exact';
            }

            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
