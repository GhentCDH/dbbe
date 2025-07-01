<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ElasticSearchService\ElasticTypeService;
use App\Model\Status;
use App\ObjectStorage\AcknowledgementManager;
use App\ObjectStorage\GenreManager;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\KeywordManager;
use App\ObjectStorage\LanguageManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\MetreManager;
use App\ObjectStorage\PersonManager;
use App\ObjectStorage\ReferenceTypeManager;
use App\ObjectStorage\RoleManager;
use App\ObjectStorage\StatusManager;
use App\ObjectStorage\TypeManager;
use App\ObjectStorage\TypeRelationTypeManager;
use App\Security\Roles;

class TypeController extends BaseController
{
    public function __construct(TypeManager $typeManager)
    {
        $this->manager = $typeManager;
        $this->templateFolder = 'Type/';
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    #[Route(path: '/types', name: 'types_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse|RedirectResponse
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMicro($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('types_search', ['request' =>  $request], 301);
    }

    /**
     * @param Request $request
     * @param ElasticTypeService $elasticTypeService
     * @param IdentifierManager $identifierManager
     * @param ManagementManager $managementManager
     * @return Response
     */
    #[Route(path: '/types/search', name: 'types_search', methods: ['GET'])]
    public function search(
        Request $request,
        ElasticTypeService $elasticTypeService,
        IdentifierManager $identifierManager,
        ManagementManager $managementManager
    ) {
        return $this->render(
            $this->templateFolder . 'overview.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'urls' => json_encode([
                    'types_search_api' => $this->generateUrl('types_search_api'),
                    'types_export_csv' => $this->generateUrl('types_export_csv'),
                    'occurrence_deps_by_type' => $this->generateUrl('occurrence_deps_by_type', ['id' => 'type_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'type_edit' => $this->generateUrl('type_edit', ['id' => 'type_id']),
                    'type_delete' => $this->generateUrl('type_delete', ['id' => 'type_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'login' => $this->generateUrl('login'),
                    'managements_add' => $this->generateUrl('types_managements_add'),
                    'managements_remove' => $this->generateUrl('types_managements_remove'),
                    'help' => $this->generateUrl('page_get', ['slug' => 'search-tips-tricks']),
                ]),
                'data' => json_encode(
                    $elasticTypeService->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
                    )
                ),
                'identifiers' => json_encode(
                    $identifierManager->getPrimaryByTypeJson('type')
                ),
                'managements' => json_encode(
                    $this->isGranted(Roles::ROLE_EDITOR_VIEW) ? $managementManager->getAllShortJson() : []
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * @param Request $request
     * @param ElasticTypeService $elasticTypeService
     * @return JsonResponse
     */
    #[Route(path: '/types/search_api', name: 'types_search_api', methods: ['GET'])]
    public function searchAPI(
        Request $request,
        ElasticTypeService $elasticTypeService
    ) {
        $this->throwErrorIfNotJson($request);
        $result = $elasticTypeService->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );

        return new JsonResponse($result);
    }

    #[Route('/types/export_csv', name: 'types_export_csv', methods: ['GET'])]
    public function exportCSV(
        Request $request,
        ElasticTypeService $elasticTypeService,
    ): Response {
        $params = $this->sanitize($request->query->all());
        $isAuthorized = $this->isGranted(Roles::ROLE_EDITOR_VIEW);
        $csvStream = $this->manager->generateCsvStream($params, $elasticTypeService, $isAuthorized);
        rewind($csvStream);
        return new Response(stream_get_contents($csvStream), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="types.csv"',
        ]);
    }

    /**
    /**
     * @param TypeRelationTypeManager $typeRelationTypeManager
     * @param PersonManager $personManager
     * @param MetreManager $metreManager
     * @param GenreManager $genreManager
     * @param KeywordManager $keywordManager
     * @param ReferenceTypeManager $referenceTypeManager
     * @param LanguageManager $languageManager
     * @param AcknowledgementManager $acknowledgementManager
     * @param StatusManager $statusManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    #[Route(path: '/types/add', name: 'type_add', methods: ['GET'])]
    public function add(
        TypeRelationTypeManager $typeRelationTypeManager,
        PersonManager $personManager,
        MetreManager $metreManager,
        GenreManager $genreManager,
        KeywordManager $keywordManager,
        ReferenceTypeManager $referenceTypeManager,
        LanguageManager $languageManager,
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
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/types/{id}', name: 'type_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    #[Route(path: '/typ/{id}', name: 'type_get_old_perma', methods: ['GET'])]
    public function getOldPerma(int $id): RedirectResponse
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('type_get', ['id' => $newId], 301);
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    #[Route(path: '/type/view/id/{id}', name: 'type_get_old', methods: ['GET'])]
    public function getOld(int $id): RedirectResponse
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('type_get', ['id' => $newId], 301);
    }

    /**
     * Get all types that have a dependency on a status
     * (document_status)
     * @param int $id status id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/statuses/{id}', name: 'type_deps_by_status', methods: ['GET'])]
    public function getDepsByStatus(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getStatusDependencies');
    }

    /**
     * Get all types that have a dependency on a person
     * (bibrole / factoid)
     * @param int $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/persons/{id}', name: 'type_deps_by_person', methods: ['GET'])]
    public function getDepsByPerson(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all types that have a dependency on a metre
     * (poem_metre)
     * @param int $id metre id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/metres/{id}', name: 'type_deps_by_metre', methods: ['GET'])]
    public function getDepsByMetre(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getMetreDependencies');
    }

    /**
     * Get all types that have a dependency on a genre
     * (document_genre)
     * @param int $id genre id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/genres/{id}', name: 'type_deps_by_genre', methods: ['GET'])]
    public function getDepsByGenre(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getGenreDependencies');
    }

    /**
     * Get all types that have a dependency on a keyword
     * (factoid / document_keyword)
     * @param int $id keyword id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/keywords/{id}', name: 'type_deps_by_keyword', methods: ['GET'])]
    public function getDepsByKeyword(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getKeywordDependencies');
    }

    /**
     * Get all types that have a dependency on an acknowledgement
     * (document_acknowledgement)
     * @param int $id acknowledgement id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/acknowledgements/{id}', name: 'type_deps_by_acknowledgement', methods: ['GET'])]
    public function getDepsByAcknowledgement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getAcknowledgementDependencies');
    }

    /**
     * Get all types that have a dependency on an occurrence
     * (factoid: based on)
     * @param int $id occurrence id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/occurrences/{id}', name: 'type_deps_by_occurrence', methods: ['GET'])]
    public function getDepsByOccurrence(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getOccurrenceDependencies');
    }

    /**
     * Get all types that have a dependency on a role
     * (bibrole)
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/roles/{id}', name: 'type_deps_by_role', methods: ['GET'])]
    public function getDepsByRole(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all types that have a dependency on an article
     * (reference)
     * @param int $id article id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/articles/{id}', name: 'type_deps_by_article', methods: ['GET'])]
    public function getDepsByArticle(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
     * Get all types that have a dependency on a blog post
     * (reference)
     * @param int $id blog post id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/blogposts/{id}', name: 'type_deps_by_blog_post', methods: ['GET'])]
    public function getDepsByBlogPost(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBlogPostDependencies');
    }

    /**
     * Get all types that have a dependency on a book
     * (reference)
     * @param int $id book id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/books/{id}', name: 'type_deps_by_book', methods: ['GET'])]
    public function getDepsByBook(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all types that have a dependency on a book chapter
     * (reference)
     * @param int $id book chapter id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/bookchapters/{id}', name: 'type_deps_by_book_chapter', methods: ['GET'])]
    public function getDepsByBookChapter(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all types that have a dependency on an online source
     * (reference)
     * @param int $id online source id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/onlinesources/{id}', name: 'type_deps_by_online_source', methods: ['GET'])]
    public function getDepsByOnlineSource(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all types that have a dependency on a PhD thesis
     * (reference)
     * @param int $id phd id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/phd_theses/{id}', name: 'type_deps_by_phd', methods: ['GET'])]
    public function getDepsByPhd(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPhdDependencies');
    }

    /**
     * Get all types that have a dependency on a bib varia
     * (reference)
     * @param int $id bib varia id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/bib_varia/{id}', name: 'type_deps_by_bib_varia', methods: ['GET'])]
    public function getDepsByBibVaria(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBibVariaDependencies');
    }

    /**
     * Get all types that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/managements/{id}', name: 'type_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types', name: 'type_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Type added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/{id}', name: 'type_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Type data successfully saved.');
        }

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/managements/add', name: 'types_managements_add', methods: ['PUT'])]
    public function addManagements(Request $request): JsonResponse
    {
        return parent::addManagements($request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/managements/remove', name: 'types_managements_remove', methods: ['PUT'])]
    public function removeManagements(Request $request): JsonResponse
    {
        return parent::removeManagements($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/types/{id}', name: 'type_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }

    /**
     * @param TypeRelationTypeManager $typeRelationTypeManager
     * @param PersonManager $personManager
     * @param MetreManager $metreManager
     * @param GenreManager $genreManager
     * @param KeywordManager $keywordManager
     * @param ReferenceTypeManager $referenceTypeManager
     * @param LanguageManager $languageManager
     * @param AcknowledgementManager $acknowledgementManager
     * @param StatusManager $statusManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    #[Route(path: '/types/{id}/edit', name: 'type_edit', methods: ['GET'])]
    public function edit(
        TypeRelationTypeManager $typeRelationTypeManager,
        PersonManager $personManager,
        MetreManager $metreManager,
        GenreManager $genreManager,
        KeywordManager $keywordManager,
        ReferenceTypeManager $referenceTypeManager,
        LanguageManager $languageManager,
        AcknowledgementManager $acknowledgementManager,
        StatusManager $statusManager,
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'urls' => json_encode([
                    'type_get' => $this->generateUrl('type_get', ['id' => $id == null ? 'type_id' : $id]),
                    'type_post' => $this->generateUrl('type_post'),
                    'type_put' => $this->generateUrl('type_put', ['id' => $id == null ? 'type_id' : $id]),
                    'types_get' => $this->generateUrl('types_get'),
                    'types_search' => $this->generateUrl('types_search'),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'historical_persons_get' => $this->generateUrl('persons_get', ['type' => 'historical']),
                    'metres_get' => $this->generateUrl('metres_get'),
                    'metres_edit' => $this->generateUrl('metres_edit'),
                    'genres_get' => $this->generateUrl('genres_get'),
                    'genres_edit' => $this->generateUrl('genres_edit'),
                    'keywords_subject_get' => $this->generateUrl('subjects_get'),
                    'keywords_subject_edit' => $this->generateUrl('subjects_edit'),
                    'keywords_type_get' => $this->generateUrl('tags_get'),
                    'keywords_type_edit' => $this->generateUrl('tags_edit'),
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
                    'statuses_get' => $this->generateUrl('statuses_get', ['type' => 'type']),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'occurrences_get' => $this->generateUrl('occurrences_get'),
                    'occurrences_search' => $this->generateUrl('occurrences_search'),
                    'dbbe_persons_get' => $this->generateUrl('persons_get', ['type' => 'dbbe']),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'type' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'typeRelationTypes' => $typeRelationTypeManager->getAllShortJson(),
                    'dbbePersons' => $personManager->getAllDBBEShortJson(),
                    'modernPersons' => $personManager->getAllModernShortJson(),
                    'metres' => $metreManager->getAllShortJson(),
                    'genres' => $genreManager->getAllShortJson(),
                    'subjectKeywords' => $keywordManager->getByTypeShortJson('subject'),
                    'typeKeywords' => $keywordManager->getByTypeShortJson('type'),
                    'referenceTypes' => $referenceTypeManager->getAllShortJson(),
                    'languages' => $languageManager->getAllShortJson(),
                    'acknowledgements' => $acknowledgementManager->getAllShortJson(),
                    'textStatuses' => $statusManager->getByTypeShortJson(Status::TYPE_TEXT),
                    'criticalStatuses' => $statusManager->getByTypeShortJson(Status::TYPE_CRITICAL),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $identifierManager->getByTypeJson('type')
                ),
                'roles' => json_encode(
                    $roleManager->getByTypeJson('type')
                ),
                'contributorRoles' => json_encode(
                    $roleManager->getContributorByTypeJson('type')
                ),
                'translationRoles' => json_encode(
                    $roleManager->getByTypeJson('translation')
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * Sanitize data from request string
     * @param  array $params
     * @return array
     */
    private function sanitize(array $params): array
    {
        $defaults = [
            'limit' => 25,
            'page' => 1,
            'ascending' => 1,
            'orderBy' => ['incipit.keyword'],
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
        if (isset($params['orderBy']) && is_string($params['orderBy'])) {
            if (isset($params['ascending']) && ($params['ascending'] == '0' || $params['ascending'] == '1')) {
                $esParams['ascending'] = intval($params['ascending']);
            } else {
                $esParams['ascending'] = $defaults['ascending'];
            }
            if (($params['orderBy']) == 'id') {
                $esParams['orderBy'] = ['id'];
            } elseif (($params['orderBy']) == 'incipit') {
                $esParams['orderBy'] = ['incipit.keyword'];
            } elseif (($params['orderBy']) == 'number_of_occurrences') {
                $esParams['orderBy'] = ['number_of_occurrences'];
            } elseif (($params['orderBy']) == 'created') {
                $esParams['orderBy'] = ['created'];
            } elseif (($params['orderBy']) == 'modified') {
                $esParams['orderBy'] = ['modified'];
            } else {
                $esParams['orderBy'] = $defaults['orderBy'];
            }
        // Don't set default order if there is a text field filter
        } elseif (!(isset($params['filters']['text']) || isset($params['filters']['comment']))) {
            $esParams['orderBy'] = $defaults['orderBy'];
        }

        // Filtering
        $filters = [];
        if (isset($params['filters']) && is_array($params['filters'])) {
            // TODO detailed sanitization
            $filters = $params['filters'];
        }

        // limit results to public if no access rights
        if (!$this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
            $filters['public'] = '1';
            unset($filters['text_status']);
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        if (!empty($filters)) {
            // sanitize text_stem
            if (!(isset($filters['text_stem'])
                && in_array($filters['text_stem'], ['original', 'stemmer']))
            ) {
                $filters['text_stem'] = 'original';
            }
            // sanitize text_fields
            if (!(isset($filters['text_fields']) && in_array($filters['text_fields'], ['text', 'title', 'all']))) {
                $filters['text_fields'] = 'text';
            }
            // sanitize text_combination
            if (!(isset($filters['text_combination'])
                && in_array($filters['text_combination'], ['any', 'all', 'phrase']))
            ) {
                $filters['text_combination'] = 'all';
            }

            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
