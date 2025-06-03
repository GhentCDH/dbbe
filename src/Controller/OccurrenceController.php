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
use App\Security\Roles;

class OccurrenceController extends BaseController
{
    public function __construct(OccurrenceManager $occurrenceManager)
    {
        $this->manager = $occurrenceManager;
        $this->templateFolder = 'Occurrence/';
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    #[Route(path: '/occurrences', name: 'occurrences_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse|RedirectResponse
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMicro($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('occurrences_search', ['request' =>  $request], 301);
    }

    /**
     * @param Request $request
     * @param ElasticOccurrenceService $elasticOccurrenceService
     * @param IdentifierManager $identifierManager
     * @param ManagementManager $managementManager
     * @return Response
     */
    #[Route(path: '/occurrences/search', name: 'occurrences_search', methods: ['GET'])]
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
                'occurrences_export_csv' => $this->generateUrl('occurrences_export_csv'),
                'type_deps_by_occurrence' => $this->generateUrl('type_deps_by_occurrence', ['id' => 'occurrence_id']),
                'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                'occurrence_edit' => $this->generateUrl('occurrence_edit', ['id' => 'occurrence_id']),
                'occurrence_delete' => $this->generateUrl('occurrence_delete', ['id' => 'occurrence_id']),
                'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                'login' => $this->generateUrl('login'),
                'managements_add' => $this->generateUrl('occurrences_managements_add'),
                'managements_remove' => $this->generateUrl('occurrences_managements_remove'),
                'help' => $this->generateUrl('page_get', ['slug' => 'search-tips-tricks']),
            ]),
            'data' => json_encode(
                $elasticOccurrenceService->searchAndAggregate(
                    $this->sanitize($request->query->all()),
                    $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
                )
            ),
            'identifiers' => json_encode(
                $identifierManager->getPrimaryByTypeJson('occurrence')
            ),
            'managements' => json_encode(
                $this->isGranted(Roles::ROLE_EDITOR_VIEW) ? $managementManager->getAllShortJson() : []
            ),
            // @codingStandardsIgnoreEnd
        ];
        return $this->render(
            'Occurrence/overview.html.twig',
            $data
        );
    }

    /**
     * @param Request $request
     * @param ElasticOccurrenceService $elasticOccurrenceService
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/search_api', name: 'occurrences_search_api', methods: ['GET'])]
    public function searchAPI(
        Request $request,
        ElasticOccurrenceService $elasticOccurrenceService
    ) {
        $this->throwErrorIfNotJson($request);
        $result = $elasticOccurrenceService->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );
        return new JsonResponse($result);
    }

    #[Route('/occurrences/export_csv', name: 'occurrences_export_csv', methods: ['GET'])]
    public function exportCSV(Request $request, ElasticOccurrenceService $elasticOccurrenceService): Response
    {
        $params = $this->sanitize($request->query->all());
        $result = $elasticOccurrenceService->searchAndAggregate($params, $this->isGranted(Roles::ROLE_VIEW_INTERNAL));

        $ids = array_column($result['data'], 'id');
        $shortDataById = [];
        foreach ($this->manager->getShort($ids) as $entry) {
            $shortDataById[$entry->getId()] = $entry;
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'id',
            'incipit',
            'verses',
            'genres',
            'subjects',
            'metres',
            'date_floor_year',
            'date_ceiling_year',
            'manuscript_id',
            'manuscript_name',
            'person'
        ]);

        foreach ($result['data'] as $item) {
            $id = $item['id'] ?? null;
            $incipit = $item['incipit'] ?? '';

            $verses = $genres = $subjects = $metres = $personData = '';
            if (isset($shortDataById[$id])) {
                $entry = $shortDataById[$id];

                $verses = implode("\n", array_map(fn($v) => $v->getVerse(), $entry->getVerses()));
                $genres = implode(' | ', array_map(fn($g) => $g->getName(), $entry->getGenres()));
                $subjects = implode(' | ', array_map(fn($s) => $s->getName(), $entry->getSubjects()));
                $metres = implode(' | ', array_map(fn($m) => $m->getName(), $entry->getMetres()));

                $personRoles = $entry->getPersonRoles();
                $roleStrings = [];
                foreach ($personRoles as $rolePair) {
                    if (!is_array($rolePair) || count($rolePair) !== 2) {
                        continue; // skip if structure unexpected
                    }
                    /** @var \App\Model\Role $roleObj */
                    $roleObj = $rolePair[0];
                    $usage = $rolePair[1];

                    $roleName = $roleObj->getName();

                    if (!is_array($usage)) {
                        continue; // safety check
                    }

                    foreach ($usage as $person) {
                        if (!$person instanceof \App\Model\Person) {
                            continue;
                        }
                        $fullName = trim($person->getFirstName() . ' ' . $person->getLastName());
                        $roleStrings[] = "{$roleName}: {$fullName}";
                    }
                }


                $personData = implode(' | ', $roleStrings);
            }

            $dateFloor = $item['date_floor_year'] ?? '';
            $dateCeiling = $item['date_ceiling_year'] ?? '';

            $manuscript = $item['manuscript'] ?? null;
            $manuscriptId = $manuscript['id'] ?? '';
            $manuscriptName = $manuscript['name'] ?? '';

            fputcsv($output, [
                $id,
                $incipit,
                $verses,
                $genres,
                $subjects,
                $metres,
                $dateFloor,
                $dateCeiling,
                $manuscriptId,
                $manuscriptName,
                $personData
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="occurrences.csv"',
        ]);
    }

    /**
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
    #[Route(path: '/occurrences/add', name: 'occurrence_add', methods: ['GET'])]
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
    #[Route(path: '/occurrences/{id}', name: 'occurrence_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    #[Route(path: '/occ/{id}', name: 'occurrence_get_old_perma', methods: ['GET'])]
    public function getOldPerma(int $id): RedirectResponse
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('occurrence_get', ['id' => $newId], 301);
    }

    /**
     * @param int $id
     * @return RedirectResponse
     */
    #[Route(path: '/occurrence/view/id/{id}', name: 'occurrence_get_old', methods: ['GET'])]
    public function getOld(int $id): RedirectResponse
    {
        // Let the 404 page handle the not found exception
        $newId = $this->manager->getNewId($id);
        return $this->redirectToRoute('occurrence_get', ['id' => $newId], 301);
    }

    /**
     * Get all occurrences that have a dependency on a manuscript
     * (document_contains)
     * @param int $id manuscript id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/manuscripts/{id}', name: 'occurrence_deps_by_manuscript', methods: ['GET'])]
    public function getDepsByManuscript(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManuscriptDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a status
     * (document_status)
     * @param int $id status id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/statuses/{id}', name: 'occurrence_deps_by_status', methods: ['GET'])]
    public function getDepsByStatus(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getStatusDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a person
     * (bibrole / factoid)
     * @param int $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/persons/{id}', name: 'occurrence_deps_by_person', methods: ['GET'])]
    public function getDepsByPerson(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a metre
     * (poem_metre)
     * @param int $id metre id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/metres/{id}', name: 'occurrence_deps_by_metre', methods: ['GET'])]
    public function getDepsByMetre(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getMetreDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a genre
     * (document_genre)
     * @param int $id genre id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/genres/{id}', name: 'occurrence_deps_by_genre', methods: ['GET'])]
    public function getDepsByGenre(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getGenreDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a keyword
     * (factoid)
     * @param int $id keyword id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/keywords/{id}', name: 'occurrence_deps_by_keyword', methods: ['GET'])]
    public function getDepsByKeyword(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getKeywordDependencies');
    }

    /**
     * Get all occurrences that have a dependency on an acknowledgement
     * (document_acknowledgement)
     * @param int $id acknowledgement id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/acknowledgements/{id}', name: 'occurrence_deps_by_acknowledgement', methods: ['GET'])]
    public function getDepsByAcknowledgement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getAcknowledgementDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a type
     * (factoid: reconstruction of)
     * @param int $id type id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/types/{id}', name: 'occurrence_deps_by_type', methods: ['GET'])]
    public function getDepsByOccurrence(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getTypeDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a role
     * (bibrole)
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/roles/{id}', name: 'occurrence_deps_by_role', methods: ['GET'])]
    public function getDepsByRole(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all occurrences that have a dependency on an article
     * (reference)
     * @param int $id article id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/articles/{id}', name: 'occurrence_deps_by_article', methods: ['GET'])]
    public function getDepsByArticle(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a blog post
     * (reference)
     * @param int $id blog post id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/blogposts/{id}', name: 'occurrence_deps_by_blog_post', methods: ['GET'])]
    public function getDepsByBlogPost(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBlogPostDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a book
     * (reference)
     * @param int $id book id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/books/{id}', name: 'occurrence_deps_by_book', methods: ['GET'])]
    public function getDepsByBook(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a book chapter
     * (reference)
     * @param int $id book chapter id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/bookchapters/{id}', name: 'occurrence_deps_by_book_chapter', methods: ['GET'])]
    public function getDepsByBookChapter(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all occurrences that have a dependency on an online source
     * (reference)
     * @param int $id online source id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/onlinesources/{id}', name: 'occurrence_deps_by_online_source', methods: ['GET'])]
    public function getDepsByOnlineSource(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a PhD thesis
     * (reference)
     * @param int $id phd id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/phd_theses/{id}', name: 'occurrence_deps_by_phd', methods: ['GET'])]
    public function getDepsByPhd(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPhdDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a bib varia
     * (reference)
     * @param int $id bib varia id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/bib_varia/{id}', name: 'occurrence_deps_by_bib_varia', methods: ['GET'])]
    public function getDepsByBibVaria(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getBibVariaDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/managements/{id}', name: 'occurrence_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences', name: 'occurrence_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Occurrence added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/{id}', name: 'occurrence_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Occurrence data successfully saved.');
        }

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/managements/add', name: 'occurrences_managements_add', methods: ['PUT'])]
    public function addManagements(Request $request): JsonResponse
    {
        return parent::addManagements($request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/managements/remove', name: 'occurrences_managements_remove', methods: ['PUT'])]
    public function removeManagements(Request $request): JsonResponse
    {
        return parent::removeManagements($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/occurrences/{id}', name: 'occurrence_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }

    /**
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
    #[Route(path: '/occurrences/{id}/edit', name: 'occurrence_edit', methods: ['GET'])]
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
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

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
                    'login' => $this->generateUrl('login'),
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
