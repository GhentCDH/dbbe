<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ElasticSearchService\ElasticPersonService;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\OfficeManager;
use App\ObjectStorage\OriginManager;
use App\ObjectStorage\PersonManager;
use App\ObjectStorage\SelfDesignationManager;
use App\Security\Roles;

class PersonController extends BaseController
{
    public function __construct(PersonManager $personManager)
    {
        $this->manager = $personManager;
        $this->templateFolder = 'Person/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons', name: 'persons_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
            if ($request->query->get('type') === 'historical') {
                return new JsonResponse(
                    $this->manager->getAllHistoricalShortJson()
                );
            }
            if ($request->query->get('type') === 'dbbe') {
                return new JsonResponse(
                    $this->manager->getAllDBBEShortJson()
                );
            }
            if ($request->query->get('type') === 'modern') {
                return new JsonResponse(
                    $this->manager->getAllModernShortJson()
                );
            }

            return new JsonResponse(
                $this->manager->getAllMiniShortJson()
            );
        }

        // Redirect to search page if not a json request
        return $this->redirectToRoute('persons_search', ['request' =>  $request], 301);
    }

    /**
     * @param Request $request
     * @param ElasticPersonService $elasticPersonService
     * @param IdentifierManager $identifierManager
     * @param ManagementManager $managementManager
     * @return Response
     */
    #[Route(path: '/persons/search', name: 'persons_search', methods: ['GET'])]
    public function search(
        Request $request,
        ElasticPersonService $elasticPersonService,
        IdentifierManager $identifierManager,
        ManagementManager $managementManager
    ) {
        return $this->render(
            'Person/overview.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'persons_search_api' => $this->generateUrl('persons_search_api'),
                    'manuscript_deps_by_person' => $this->generateUrl('manuscript_deps_by_person', ['id' => 'person_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'occurrence_deps_by_person' => $this->generateUrl('occurrence_deps_by_person', ['id' => 'person_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_person' => $this->generateUrl('type_deps_by_person', ['id' => 'person_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'article_deps_by_person' => $this->generateUrl('article_deps_by_person', ['id' => 'person_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'book_deps_by_person' => $this->generateUrl('book_deps_by_person', ['id' => 'person_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_chapter_deps_by_person' => $this->generateUrl('book_chapter_deps_by_person', ['id' => 'person_id']),
                    'book_chapter_get' => $this->generateUrl('book_chapter_get', ['id' => 'book_chapter_id']),
                    'content_deps_by_person' => $this->generateUrl('content_deps_by_person', ['id' => 'person_id']),
                    'contents_edit' => $this->generateUrl('contents_edit', ['id' => 'content_id']),
                    'blog_post_deps_by_person' => $this->generateUrl('blog_post_deps_by_person', ['id' => 'person_id']),
                    'blog_post_get' => $this->generateUrl('blog_post_get', ['id' => 'blog_post_id']),
                    'phd_deps_by_person' => $this->generateUrl('phd_deps_by_person', ['id' => 'person_id']),
                    'phd_get' => $this->generateUrl('phd_get', ['id' => 'phd_id']),
                    'bib_varia_deps_by_person' => $this->generateUrl('bib_varia_deps_by_person', ['id' => 'person_id']),
                    'bib_varia_get' => $this->generateUrl('bib_varia_get', ['id' => 'bib_varia_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'person_edit' => $this->generateUrl('person_edit', ['id' => 'person_id']),
                    'person_merge' => $this->generateUrl('person_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'person_delete' => $this->generateUrl('person_delete', ['id' => 'person_id']),
                    'persons_get' => $this->generateUrl('persons_get'),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    'managements_add' => $this->generateUrl('persons_managements_add'),
                    'managements_remove' => $this->generateUrl('persons_managements_remove'),
                    // @codingStandardsIgnoreEnd
                ]),
                'data' => json_encode(
                    $elasticPersonService->searchAndAggregate(
                        $this->sanitize($request->query->all(), $identifierManager),
                        $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
                    )
                ),
                'identifiers' => json_encode(
                    $identifierManager->getPrimaryByTypeJson('person')
                ),
                'managements' => json_encode(
                    $this->isGranted(Roles::ROLE_EDITOR_VIEW) ? $managementManager->getAllShortJson() : []
                ),
            ]
        );
    }

    /**
     * @param Request $request
     * @param ElasticPersonService $elasticPersonService
     * @param IdentifierManager $identifierManager
     * @return JsonResponse
     */
    #[Route(path: '/persons/search_api', name: 'persons_search_api', methods: ['GET'])]
    public function searchAPI(
        Request $request,
        ElasticPersonService $elasticPersonService,
        IdentifierManager $identifierManager
    ) {
        $result = $elasticPersonService->searchAndAggregate(
            $this->sanitize($request->query->all(), $identifierManager),
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );

        return new JsonResponse($result);
    }

    /**
     * @param OfficeManager $officeManager
     * @param OriginManager $originManager
     * @param SelfDesignationManager $selfDesignationManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @return Response
     */
    #[Route(path: '/persons/add', name: 'person_add', methods: ['GET'])]
    public function add(
        OfficeManager $officeManager,
        OriginManager $originManager,
        SelfDesignationManager $selfDesignationManager,
        ManagementManager $managementManager,
        IdentifierManager $identifierManager
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        $args = func_get_args();
        $args[] = null;

        return call_user_func_array([$this, 'edit'], $args);
    }

    /**
     * @param  int    $id person id
     * @param Request $request
     * @return Response
     */
    #[Route(path: '/persons/{id}', name: 'person_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all persons that have a dependency on an office
     * (person_occupation)
     * @param  int    $id office id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/offices/{id}', name: 'person_deps_by_office', methods: ['GET'])]
    public function getDepsByOffice(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getOfficeDependencies');
    }

    /**
     * Get all persons that have a dependency on a region
     * (factoid: origination)
     * @param  int    $id region id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/regions/{id}', name: 'person_deps_by_region', methods: ['GET'])]
    public function getDepsByRegion(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * Get all persons that have a dependency on a self designation
     * (person_self_designation)
     * @param  int    $id self designation id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/self-designations/{id}', name: 'person_deps_by_self_designation', methods: ['GET'])]
    public function getDepsBySelfDesignation(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getSelfDesignationDependencies');
    }

    /**
     * Get all persons that have a dependency on an article
     * (reference)
     * @param int $id article id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/articles/{id}', name: 'person_deps_by_article', methods: ['GET'])]
    public function getDepsByArticle(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
     * Get all persons that have a dependency on a blog post
     * (reference)
     * @param int $id blog post id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/blogposts/{id}', name: 'person_deps_by_blog_post', methods: ['GET'])]
    public function getDepsByBlogPost(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBlogPostDependencies');
    }

    /**
     * Get all persons that have a dependency on a book
     * (reference)
     * @param int $id book id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/books/{id}', name: 'person_deps_by_book', methods: ['GET'])]
    public function getDepsByBook(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all persons that have a dependency on a book chapter
     * (reference)
     * @param int $id book chapter id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/bookchapters/{id}', name: 'person_deps_by_book_chapter', methods: ['GET'])]
    public function getDepsByBookChapter(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all persons that have a dependency on an online source
     * (reference)
     * @param int $id online source id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/onlinesources/{id}', name: 'person_deps_by_online_source', methods: ['GET'])]
    public function getDepsByOnlineSource(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all persons that have a dependency on a PhD thesis
     * (reference)
     * @param int $id phd id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/phd_theses/{id}', name: 'person_deps_by_phd', methods: ['GET'])]
    public function getDepsByPhd(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPhdDependencies');
    }

    /**
     * Get all persons that have a dependency on a bib varia
     * (reference)
     * @param int $id bib varia id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/bib_varia/{id}', name: 'person_deps_by_bib_varia', methods: ['GET'])]
    public function getDepsByBibVaria(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBibVariaDependencies');
    }

    /**
     * Get all persons that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/managements/{id}', name: 'person_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons', name: 'person_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Person added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/{id}', name: 'person_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Person data successfully saved.');
        }

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/managements/add', name: 'persons_managements_add', methods: ['PUT'])]
    public function addManagements(Request $request): JsonResponse
    {
        return parent::addManagements($request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/managements/remove', name: 'persons_managements_remove', methods: ['PUT'])]
    public function removeManagements(Request $request): JsonResponse
    {
        return parent::removeManagements($request);
    }

    /**
     * @param  int    $primaryId   first person id (will stay)
     * @param  int    $secondaryId second person id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/{primaryId}/{secondaryId}', name: 'person_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/persons/{id}', name: 'person_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }

    /**
     * @param OfficeManager $officeManager
     * @param OriginManager $originManager
     * @param SelfDesignationManager $selfDesignationManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param int|null $id person id
     * @return Response
     */
    #[Route(path: '/persons/{id}/edit', name: 'person_edit', methods: ['GET'])]
    public function edit(
        OfficeManager $officeManager,
        OriginManager $originManager,
        SelfDesignationManager $selfDesignationManager,
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Person/edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    'person_get' => $this->generateUrl('person_get', ['id' => $id == null ? 'person_id' : $id]),
                    'person_post' => $this->generateUrl('person_post'),
                    'person_put' => $this->generateUrl('person_put', ['id' => $id == null ? 'person_id' : $id]),
                    'offices_get' => $this->generateUrl('offices_get'),
                    'offices_edit' => $this->generateUrl('offices_edit'),
                    'origins_get' => $this->generateUrl('origins_get', ['type' => 'person']),
                    'origins_edit' => $this->generateUrl('origins_edit'),
                    'self_designations_get' => $this->generateUrl('self_designations_get'),
                    'self_designations_edit' => $this->generateUrl('self_designations_edit'),
                    'articles_get' => $this->generateUrl('articles_get'),
                    'blog_posts_get' => $this->generateUrl('blog_posts_get'),
                    'books_get' => $this->generateUrl('books_get'),
                    'book_chapters_get' => $this->generateUrl('book_chapters_get'),
                    'online_sources_get' => $this->generateUrl('online_sources_get'),
                    'phds_get' => $this->generateUrl('phds_get'),
                    'bib_varias_get' => $this->generateUrl('bib_varias_get'),
                    'bibliographies_search' => $this->generateUrl('bibliographies_search'),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                ]),
                'data' => json_encode([
                    'person' =>
                    empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'offices' => $officeManager->getAllJson(),
                    'origins' => $originManager->getByTypeShortJson('person'),
                    'selfDesignations' => $selfDesignationManager->getAllJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $identifierManager->getByTypeJson('person')
                ),
                'roles' => json_encode([]),
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
                    $esParams['orderBy'] = [
                        'death_date_ceiling_year',
                        'death_date_floor_year',
                        'born_date_ceiling_year',
                        'born_date_floor_year',
                    ];
                } else {
                    $esParams['orderBy'] = [
                        'born_date_floor_year',
                        'born_date_ceiling_year',
                        'death_date_floor_year',
                        'death_date_ceiling_year',
                    ];
                }
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
            $identifiers = array_keys($identifierManager->getPrimaryByType('person'));

            foreach (array_keys($params['filters']) as $key) {
                switch ($key) {
                    case 'role':
                    case 'self_designation':
                    case 'office':
                    case 'origin':
                        if (is_array($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'role_op':
                    case 'self_designation_op':
                    case 'office_op':
                    case 'origin_op':
                    case 'name':
                    case 'comment':
                    case 'date_search_type':
                        if (is_string($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'historical':
                    case 'modern':
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
            $filters['historical'] = '1';
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        // sanitize date search type
        if (!(isset($filters['date_search_type'])
            && in_array($filters['date_search_type'], ['exact', 'included', 'include', 'overlap']))) {
            $filters['date_search_type'] = 'exact';
        }

        $esParams['filters'] = $filters;

        return $esParams;
    }
}
