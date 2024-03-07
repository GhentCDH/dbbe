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
     * @Route("/types", name="types_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function getAll(Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMicro($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('types_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/types/search", name="types_search", methods={"GET"})
     * @param Request $request
     * @param ElasticTypeService $elasticTypeService
     * @param IdentifierManager $identifierManager
     * @param ManagementManager $managementManager
     * @return Response
     */
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
                    'occurrence_deps_by_type' => $this->generateUrl('occurrence_deps_by_type', ['id' => 'type_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'type_edit' => $this->generateUrl('type_edit', ['id' => 'type_id']),
                    'type_delete' => $this->generateUrl('type_delete', ['id' => 'type_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
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
     * @Route("/types/search_api", name="types_search_api", methods={"GET"})
     * @param Request $request
     * @param ElasticTypeService $elasticTypeService
     * @return JsonResponse
     */
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

    /**
     * @Route("/types/add", name="type_add", methods={"GET"})
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
     * @Route("/types/{id}", name="type_get", methods={"GET"})
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @Route("/typ/{id}", name="type_get_old_perma", methods={"GET"})
     * @param int $id
     * @return RedirectResponse
     */
    public function getOldPerma(int $id)
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('type_get', ['id' => $newId], 301);
    }

    /**
     * @Route("/type/view/id/{id}", name="type_get_old", methods={"GET"})
     * @param int $id
     * @return RedirectResponse
     */
    public function getOld(int $id)
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('type_get', ['id' => $newId], 301);
    }

    /**
     * Get all types that have a dependency on a status
     * (document_status)
     * @Route("/types/statuses/{id}", name="type_deps_by_status", methods={"GET"})
     * @param int $id status id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByStatus(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getStatusDependencies');
    }

    /**
     * Get all types that have a dependency on a person
     * (bibrole / factoid)
     * @Route("/types/persons/{id}", name="type_deps_by_person", methods={"GET"})
     * @param int $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all types that have a dependency on a metre
     * (poem_metre)
     * @Route("/types/metres/{id}", name="type_deps_by_metre", methods={"GET"})
     * @param int $id metre id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByMetre(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getMetreDependencies');
    }

    /**
     * Get all types that have a dependency on a genre
     * (document_genre)
     * @Route("/types/genres/{id}", name="type_deps_by_genre", methods={"GET"})
     * @param int $id genre id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByGenre(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getGenreDependencies');
    }

    /**
     * Get all types that have a dependency on a keyword
     * (factoid / document_keyword)
     * @Route("/types/keywords/{id}", name="type_deps_by_keyword", methods={"GET"})
     * @param int $id keyword id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByKeyword(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getKeywordDependencies');
    }

    /**
     * Get all types that have a dependency on an acknowledgement
     * (document_acknowledgement)
     * @Route("/types/acknowledgements/{id}", name="type_deps_by_acknowledgement", methods={"GET"})
     * @param int $id acknowledgement id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByAcknowledgement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getAcknowledgementDependencies');
    }

    /**
     * Get all types that have a dependency on an occurrence
     * (factoid: based on)
     * @Route("/types/occurrences/{id}", name="type_deps_by_occurrence", methods={"GET"})
     * @param int $id occurrence id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByOccurrence(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOccurrenceDependencies');
    }

    /**
     * Get all types that have a dependency on a role
     * (bibrole)
     * @Route("/types/roles/{id}", name="type_deps_by_role", methods={"GET"})
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all types that have a dependency on an article
     * (reference)
     * @Route("/types/articles/{id}", name="type_deps_by_article", methods={"GET"})
     * @param int $id article id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByArticle(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
     * Get all types that have a dependency on a blog post
     * (reference)
     * @Route("/types/blogposts/{id}", name="type_deps_by_blog_post", methods={"GET"})
     * @param int $id blog post id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBlogPost(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBlogPostDependencies');
    }

    /**
     * Get all types that have a dependency on a book
     * (reference)
     * @Route("/types/books/{id}", name="type_deps_by_book", methods={"GET"})
     * @param int $id book id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBook(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all types that have a dependency on a book chapter
     * (reference)
     * @Route("/types/bookchapters/{id}", name="type_deps_by_book_chapter", methods={"GET"})
     * @param int $id book chapter id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBookChapter(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all types that have a dependency on an online source
     * (reference)
     * @Route("/types/onlinesources/{id}", name="type_deps_by_online_source", methods={"GET"})
     * @param int $id online source id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByOnlineSource(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all types that have a dependency on a PhD thesis
     * (reference)
     * @Route("/types/phd_theses/{id}", name="type_deps_by_phd", methods={"GET"})
     * @param int $id phd id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByPhd(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPhdDependencies');
    }

    /**
     * Get all types that have a dependency on a bib varia
     * (reference)
     * @Route("/types/bib_varia/{id}", name="type_deps_by_bib_varia", methods={"GET"})
     * @param int $id bib varia id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBibVaria(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBibVariaDependencies');
    }

    /**
     * Get all types that have a dependency on a management collection
     * (reference)
     * @Route("/types/managements/{id}", name="type_deps_by_management", methods={"GET"})
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @Route("/types", name="type_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Type added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/types/{id}", name="type_put", methods={"PUT"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Type data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/types/managements/add", name="types_managements_add", methods={"PUT"})
     * @param Request $request
     * @return JsonResponse
     */
    public function addManagements(Request $request)
    {
        return parent::addManagements($request);
    }

    /**
     * @Route("/types/managements/remove", name="types_managements_remove", methods={"PUT"})
     * @param Request $request
     * @return JsonResponse
     */
    public function removeManagements(Request $request)
    {
        return parent::removeManagements($request);
    }

    /**
     * @Route("/types/{id}", name="type_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return Response
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/types/{id}/edit", name="type_edit", methods={"GET"})
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
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
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
