<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Model\Status;
use App\ObjectStorage\AcknowledgementManager;
use App\ObjectStorage\GenreManager;
use App\ObjectStorage\KeywordManager;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\MetreManager;
use App\ObjectStorage\OccurrenceManager;
use App\ObjectStorage\PersonManager;
use App\ObjectStorage\ReferenceTypeManager;
use App\ObjectStorage\RoleManager;
use App\ObjectStorage\StatusManager;
use App\ElasticSearchService\ElasticOccurrenceService;

class OccurrenceController extends BaseController
{
    public function __construct(OccurrenceManager $occurrenceManager)
    {
        $this->manager = $occurrenceManager;
        $this->templateFolder = 'Occurrence/';
    }

    /**
     * @Route("/occurrences", name="occurrences_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function getAll(Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMicro($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('occurrences_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/occurrences/search", name="occurrences_search", methods={"GET"})
     * @param Request $request
     * @param ElasticOccurrenceService $elasticOccurrenceService
     * @param IdentifierManager $identifierManager
     * @param ManagementManager $managementManager
     * @return Response
     */
    public function search(
        Request $request,
        ElasticOccurrenceService $elasticOccurrenceService,
        IdentifierManager $identifierManager,
        ManagementManager $managementManager
    ) {
        $data = [
            // @codingStandardsIgnoreStart Generic.Files.LineLength
            'urls' => json_encode([
                'occurrences_search_api' => $this->generateUrl('occurrences_search_api'),
                'type_deps_by_occurrence' => $this->generateUrl('type_deps_by_occurrence', ['id' => 'occurrence_id']),
                'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                'occurrence_edit' => $this->generateUrl('occurrence_edit', ['id' => 'occurrence_id']),
                'occurrence_delete' => $this->generateUrl('occurrence_delete', ['id' => 'occurrence_id']),
                'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('idci_keycloak_security_auth_connect') : $this->generateUrl('saml_login'),
                'managements_add' => $this->generateUrl('occurrences_managements_add'),
                'managements_remove' => $this->generateUrl('occurrences_managements_remove'),
                'help' => $this->generateUrl('page_get', ['slug' => 'search-tips-tricks']),
            ]),
            'data' => json_encode(
                $elasticOccurrenceService->searchAndAggregate(
                    $this->sanitize($request->query->all()),
                    $this->isGranted('ROLE_VIEW_INTERNAL')
                )
            ),
            'identifiers' => json_encode(
                $identifierManager->getPrimaryByTypeJson('occurrence')
            ),
            'managements' => json_encode(
                $this->isGranted('ROLE_EDITOR_VIEW') ? $managementManager->getAllShortJson() : []
            ),
            // @codingStandardsIgnoreEnd
        ];
        return $this->render(
            'Occurrence/overview.html.twig',
            $data
        );
    }

    /**
     * @Route("/occurrences/search_api", name="occurrences_search_api", methods={"GET"})
     * @param Request $request
     * @param ElasticOccurrenceService $elasticOccurrenceService
     * @return JsonResponse
     */
    public function searchAPI(
        Request $request,
        ElasticOccurrenceService $elasticOccurrenceService
    ) {
        $this->throwErrorIfNotJson($request);
        $result = $elasticOccurrenceService->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/occurrences/add", name="occurrence_add", methods={"GET"})
     * @param Request $request
     * @param PersonManager $personManager
     * @param MetreManager $metreManager
     * @param GenreManager $genreManager
     * @param KeywordManager $keywordManager
     * @param ReferenceTypeManager $referenceTypeManager
     * @param AcknowledgementManager $acknowledgementManager
     * @param StatusManager $statusManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    public function add(
        Request $request,
        PersonManager $personManager,
        MetreManager $metreManager,
        GenreManager $genreManager,
        KeywordManager $keywordManager,
        ReferenceTypeManager $referenceTypeManager,
        AcknowledgementManager $acknowledgementManager,
        StatusManager $statusManager,
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
     * @Route("/occurrences/{id}", name="occurrence_get", methods={"GET"})
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @Route("/occ/{id}", name="occurrence_get_old_perma", methods={"GET"})
     * @param int $id
     * @return RedirectResponse
     */
    public function getOldPerma(int $id)
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('occurrence_get', ['id' => $newId], 301);
    }

    /**
     * @Route("/occurrence/view/id/{id}", name="occurrence_get_old", methods={"GET"})
     * @param int $id
     * @return RedirectResponse
     */
    public function getOld(int $id)
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('occurrence_get', ['id' => $newId], 301);
    }

    /**
     * Get all occurrences that have a dependency on a manuscript
     * (document_contains)
     * @Route("/occurrences/manuscripts/{id}", name="occurrence_deps_by_manuscript", methods={"GET"})
     * @param int $id manuscript id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByManuscript(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManuscriptDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a status
     * (document_status)
     * @Route("/occurrences/statuses/{id}", name="occurrence_deps_by_status", methods={"GET"})
     * @param int $id status id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByStatus(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getStatusDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a person
     * (bibrole / factoid)
     * @Route("/occurrences/persons/{id}", name="occurrence_deps_by_person", methods={"GET"})
     * @param int $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a metre
     * (poem_metre)
     * @Route("/occurrences/metres/{id}", name="occurrence_deps_by_metre", methods={"GET"})
     * @param int $id metre id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByMetre(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getMetreDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a genre
     * (document_genre)
     * @Route("/occurrences/genres/{id}", name="occurrence_deps_by_genre", methods={"GET"})
     * @param int $id genre id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByGenre(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getGenreDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a keyword
     * (factoid)
     * @Route("/occurrences/keywords/{id}", name="occurrence_deps_by_keyword", methods={"GET"})
     * @param int $id keyword id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByKeyword(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getKeywordDependencies');
    }

    /**
     * Get all occurrences that have a dependency on an acknowledgement
     * (document_acknowledgement)
     * @Route("/occurrences/acknowledgements/{id}", name="occurrence_deps_by_acknowledgement", methods={"GET"})
     * @param int $id acknowledgement id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByAcknowledgement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getAcknowledgementDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a type
     * (factoid: reconstruction of)
     * @Route("/occurrences/types/{id}", name="occurrence_deps_by_type", methods={"GET"})
     * @param int $id type id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByOccurrence(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getTypeDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a role
     * (bibrole)
     * @Route("/occurrences/roles/{id}", name="occurrence_deps_by_role", methods={"GET"})
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all occurrences that have a dependency on an article
     * (reference)
     * @Route("/occurrences/articles/{id}", name="occurrence_deps_by_article", methods={"GET"})
     * @param int $id article id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByArticle(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a blog post
     * (reference)
     * @Route("/occurrences/blogposts/{id}", name="occurrence_deps_by_blog_post", methods={"GET"})
     * @param int $id blog post id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBlogPost(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBlogPostDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a book
     * (reference)
     * @Route("/occurrences/books/{id}", name="occurrence_deps_by_book", methods={"GET"})
     * @param int $id book id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBook(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a book chapter
     * (reference)
     * @Route("/occurrences/bookchapters/{id}", name="occurrence_deps_by_book_chapter", methods={"GET"})
     * @param int $id book chapter id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBookChapter(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all occurrences that have a dependency on an online source
     * (reference)
     * @Route("/occurrences/onlinesources/{id}", name="occurrence_deps_by_online_source", methods={"GET"})
     * @param int $id online source id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByOnlineSource(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a PhD thesis
     * (reference)
     * @Route("/occurrences/phd_theses/{id}", name="occurrence_deps_by_phd", methods={"GET"})
     * @param int $id phd id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByPhd(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPhdDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a bib varia
     * (reference)
     * @Route("/occurrences/bib_varia/{id}", name="occurrence_deps_by_bib_varia", methods={"GET"})
     * @param int $id bib varia id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByBibVaria(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBibVariaDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a management collection
     * (reference)
     * @Route("/occurrences/managements/{id}", name="occurrence_deps_by_management", methods={"GET"})
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @Route("/occurrences", name="occurrence_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Occurrence added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/occurrences/{id}", name="occurrence_put", methods={"PUT"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Occurrence data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/occurrences/managements/add", name="occurrences_managements_add", methods={"PUT"})
     * @param Request $request
     * @return JsonResponse
     */
    public function addManagements(Request $request)
    {
        return parent::addManagements($request);
    }

    /**
     * @Route("/occurrences/managements/remove", name="occurrences_managements_remove", methods={"PUT"})
     * @param Request $request
     * @return JsonResponse
     */
    public function removeManagements(Request $request)
    {
        return parent::removeManagements($request);
    }

    /**
     * @Route("/occurrences/{id}", name="occurrence_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return Response
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/occurrences/{id}/edit", name="occurrence_edit", methods={"GET"})
     * @param Request $request
     * @param PersonManager $personManager
     * @param MetreManager $metreManager
     * @param GenreManager $genreManager
     * @param KeywordManager $keywordManager
     * @param ReferenceTypeManager $referenceTypeManager
     * @param AcknowledgementManager $acknowledgementManager
     * @param StatusManager $statusManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    public function edit(
        Request $request,
        PersonManager $personManager,
        MetreManager $metreManager,
        GenreManager $genreManager,
        KeywordManager $keywordManager,
        ReferenceTypeManager $referenceTypeManager,
        AcknowledgementManager $acknowledgementManager,
        StatusManager $statusManager,
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        $occurrenceJson = null;
        $clone = false;
        if (!empty($id)) {
            $occurrenceJson = $this->manager->getFull($id)->getJson();
            if (!empty($request->query->get('clone')) && $request->query->get('clone') === '1') {
                $clone = true;
                $id = null;
                unset($occurrenceJson['id']);
                if (isset($occurrenceJson['verses'])) {
                    foreach (array_keys($occurrenceJson['verses']) as $index) {
                        $occurrenceJson['verses'][$index]['id'] = null;
                    }
                }
                if (isset($occurrenceJson['bibliography'])) {
                    foreach (array_keys($occurrenceJson['bibliography']) as $index) {
                        unset($occurrenceJson['bibliography'][$index]['id']);
                    }
                }
            }
        }

        return $this->render(
            'Occurrence/edit.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'clone' => $clone,
                'urls' => json_encode([
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => $id == null ? 'occurrence_id' : $id]),
                    'occurrence_post' => $this->generateUrl('occurrence_post'),
                    'occurrence_put' => $this->generateUrl('occurrence_put', ['id' => $id == null ? 'occurrence_id' : $id]),
                    'manuscripts_get' => $this->generateUrl('manuscripts_get'),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'manuscripts_search' => $this->generateUrl('manuscripts_search'),
                    'verse_variant_get' => $this->generateUrl('verse_variant_get', ['groupId' => 'verse_variant_id']),
                    'verse_search' => $this->generateUrl('verse_search'),
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
                    'image_get' => $this->generateUrl('image_get', ['id' => 'image_id']),
                    'image_post' => $this->generateUrl('image_post'),
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
                    'statuses_get' => $this->generateUrl('statuses_get', ['type' => 'occurrence']),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'dbbe_persons_get' => $this->generateUrl('persons_get', ['type' => 'dbbe']),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('idci_keycloak_security_auth_connect') : $this->generateUrl('saml_login'),
                ]),
                'data' => json_encode([
                    'clone' => $clone,
                    'occurrence' => $occurrenceJson,
                    'dbbePersons' => $personManager->getAllDBBEShortJson(),
                    'metres' => $metreManager->getAllShortJson(),
                    'genres' => $genreManager->getAllShortJson(),
                    'keywords' => $keywordManager->getByTypeShortJson('subject'),
                    'referenceTypes' => $referenceTypeManager->getAllShortJson(),
                    'acknowledgements' => $acknowledgementManager->getAllShortJson(),
                    'textStatuses' => $statusManager->getByTypeShortJson(Status::OCCURRENCE_TEXT),
                    'recordStatuses' => $statusManager->getByTypeShortJson(Status::OCCURRENCE_RECORD),
                    'dividedStatuses' => $statusManager->getByTypeShortJson(Status::OCCURRENCE_DIVIDED),
                    'sourceStatuses' => $statusManager->getByTypeShortJson(Status::OCCURRENCE_SOURCE),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $identifierManager->getByTypeJson('occurrence')
                ),
                'roles' => json_encode(
                    $roleManager->getByTypeJson('occurrence')
                ),
                'contributorRoles' => json_encode(
                    $roleManager->getContributorByTypeJson('occurrence')
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
            } elseif (($params['orderBy']) == 'manuscript') {
                $esParams['orderBy'] = ['manuscript.name.keyword'];
            } elseif (($params['orderBy']) == 'date') {
                // when sorting in descending order => sort by ceiling, else: sort by floor
                if (isset($params['ascending']) && $params['ascending'] == 0) {
                    $esParams['orderBy'] = ['date_ceiling_year', 'date_floor_year'];
                } else {
                    $esParams['orderBy'] = ['date_floor_year', 'date_ceiling_year'];
                }
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
        if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
            $filters['public'] = '1';
            unset($filters['text_status']);
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        if (!empty($filters)) {
            // sanitize text_stem
            if (!(isset($filters['text_stem']) && in_array($filters['text_stem'], ['original', 'stemmer']))) {
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

            // sanitize date search type
            if (!(isset($filters['date_search_type'])
                && in_array($filters['date_search_type'], ['exact', 'included', 'include', 'overlap']))
            ) {
                $filters['date_search_type'] = 'exact';
            }

            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
