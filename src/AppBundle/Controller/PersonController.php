<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonController extends EditController
{
    /**
     * @var string
     */
    const MANAGER = 'person_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Person:';

    /**
     * @Route("/persons", name="persons_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            if ($request->query->get('type') === 'historical') {
                return new JsonResponse(
                    $this->get(static::MANAGER)->getAllHistoricalShortJson()
                );
            }
            if ($request->query->get('type') === 'dbbe') {
                return new JsonResponse(
                    $this->get(static::MANAGER)->getAllDBBEShortJson()
                );
            }
            if ($request->query->get('type') === 'modern') {
                return new JsonResponse(
                    $this->get(static::MANAGER)->getAllModernShortJson()
                );
            }

            return new JsonResponse(
                $this->get(static::MANAGER)->getAllMiniShortJson()
            );
        }

        // Redirect to search page if not a json request
        return $this->redirectToRoute('persons_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/persons/search", name="persons_search")
     * @Method("GET")
     * @param Request $request
     * @return Response
     */
    public function search(Request $request)
    {
        return $this->render(
            'AppBundle:Person:overview.html.twig',
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
                    'login' => $this->generateUrl('saml_login'),
                    'managements_add' => $this->generateUrl('persons_managements_add'),
                    'managements_remove' => $this->generateUrl('persons_managements_remove'),
                    // @codingStandardsIgnoreEnd
                ]),
                'data' => json_encode(
                    $this->get('person_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'identifiers' => json_encode(
                    $this->get('identifier_manager')->getPrimaryByTypeJson('person')
                ),
                'managements' => json_encode(
                    $this->isGranted('ROLE_EDITOR_VIEW') ? $this->get('management_manager')->getAllShortJson() : []
                ),
            ]
        );
    }

    /**
     * @Route("/persons/search_api", name="persons_search_api")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function searchAPI(Request $request)
    {
        $result = $this->get('person_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/persons/add", name="person_add")
     * @Method("GET")
     * @param Request $request
     * @return Response
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/persons/{id}", name="person_get")
     * @Method("GET")
     * @param  int    $id person id
     * @param Request $request
     * @return Response
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all persons that have a dependency on an office
     * (person_occupation)
     * @Route("/persons/offices/{id}", name="person_deps_by_office")
     * @Method("GET")
     * @param  int    $id office id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByOffice(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOfficeDependencies');
    }

    /**
     * Get all persons that have a dependency on a region
     * (factoid: origination)
     * @Route("/persons/regions/{id}", name="person_deps_by_region")
     * @Method("GET")
     * @param  int    $id region id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByRegion(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * Get all persons that have a dependency on a self designation
     * (person_self_designation)
     * @Route("/persons/self-designations/{id}", name="person_deps_by_self_designation")
     * @Method("GET")
     * @param  int    $id self designation id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsBySelfDesignation(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getSelfDesignationDependencies');
    }

    /**
     * Get all persons that have a dependency on an article
     * (reference)
     * @Route("/persons/articles/{id}", name="person_deps_by_article")
     * @Method("GET")
     * @param  int    $id article id
     * @param Request $request
     */
    public function getDepsByArticle(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
     * Get all persons that have a dependency on a blog post
     * (reference)
     * @Route("/persons/blogposts/{id}", name="person_deps_by_blog_post")
     * @Method("GET")
     * @param  int    $id blog post id
     * @param Request $request
     */
    public function getDepsByBlogPost(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBlogPostDependencies');
    }

    /**
    * Get all persons that have a dependency on a book
    * (reference)
    * @Route("/persons/books/{id}", name="person_deps_by_book")
    * @Method("GET")
    * @param  int    $id book id
    * @param Request $request
    */
    public function getDepsByBook(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all persons that have a dependency on a book chapter
     * (reference)
     * @Route("/persons/bookchapters/{id}", name="person_deps_by_book_chapter")
     * @Method("GET")
     * @param  int    $id book chapter id
     * @param Request $request
     */
    public function getDepsByBookChapter(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all persons that have a dependency on an online source
     * (reference)
     * @Route("/persons/onlinesources/{id}", name="person_deps_by_online_source")
     * @Method("GET")
     * @param  int    $id online source id
     * @param Request $request
     */
    public function getDepsByOnlineSource(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all persons that have a dependency on a PhD thesis
     * (reference)
     * @Route("/persons/phd_theses/{id}", name="person_deps_by_phd")
     * @Method("GET")
     * @param  int    $id phd id
     * @param Request $request
     */
    public function getDepsByPhd(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPhdDependencies');
    }

    /**
     * Get all persons that have a dependency on a bib varia
     * (reference)
     * @Route("/persons/bib_varia/{id}", name="person_deps_by_bib_varia")
     * @Method("GET")
     * @param  int    $id bib varia id
     * @param Request $request
     */
    public function getDepsByBibVaria(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBibVariaDependencies');
    }

    /**
     * Get all persons that have a dependency on a management collection
     * (reference)
     * @Route("/persons/managements/{id}", name="person_deps_by_management")
     * @Method("GET")
     * @param  int    $id management id
     * @param Request $request
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @Route("/persons", name="person_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Person added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/persons/{id}", name="person_put")
     * @Method("PUT")
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Person data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/persons/managements/add", name="persons_managements_add")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function addManagements(Request $request)
    {
        return parent::addManagements($request);
    }

    /**
     * @Route("/persons/managements/remove", name="persons_managements_remove")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function removeManagements(Request $request)
    {
        return parent::removeManagements($request);
    }

    /**
     * @Route("/persons/{primaryId}/{secondaryId}", name="person_merge")
     * @Method("PUT")
     * @param  int    $primaryId   first person id (will stay)
     * @param  int    $secondaryId second person id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/persons/{id}", name="person_delete")
     * @Method("DELETE")
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/persons/{id}/edit", name="person_edit")
     * @Method("GET")
     * @param  int|null $id person id
     * @param Request $request
     * @return Response
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Person:edit.html.twig',
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
                    'login' => $this->generateUrl('saml_login'),
                ]),
                'data' => json_encode([
                    'person' =>
                        empty($id)
                        ? null
                        : $this->get('person_manager')->getFull($id)->getJson(),
                    'offices' => $this->get('office_manager')->getAllJson(),
                    'origins' => $this->get('origin_manager')->getByTypeShortJson('person'),
                    'selfDesignations' => $this->get('self_designation_manager')->getAllJson(),
                    'managements' => $this->get('management_manager')->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $this->get('identifier_manager')->getByTypeJson('person')
                ),
                'roles' => json_encode([]),
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
            $identifiers = array_keys($this->get('identifier_manager')->getPrimaryByType('person'));

            foreach (array_keys($params['filters']) as $key) {
                switch ($key) {
                    case 'name':
                    case 'self_designation':
                    case 'comment':
                    case 'date_search_type':
                        if (is_string($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'historical':
                    case 'modern':
                    case 'role':
                    case 'office':
                    case 'origin':
                    case 'public':
                    case 'management':
                        if (is_numeric($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'management_inverse':
                        if (
                            is_string($params['filters'][$key])
                            && (
                                $params['filters'][$key] == 'true'
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

                if (in_array($key, $identifiers)) {
                    if (is_string($params['filters'][$key])) {
                        $filters[$key] = $params['filters'][$key];
                    }
                }
            }
        }

        // limit results to public if no access rights
        if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
            $filters['public'] = '1';
            $filters['historical'] = '1';
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        // sanitize date search type
        if (!(isset($filters['date_search_type']) && in_array($filters['date_search_type'], ['exact', 'included', 'include', 'overlap']))) {
            $filters['date_search_type'] = 'exact';
        }

        $esParams['filters'] = $filters;

        return $esParams;
    }
}
