<?php

namespace AppBundle\Controller;

use AppBundle\Model\Status;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TypeController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'type_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Type:';

    /**
     * @Route("/types/search", name="types_search")
     * @Method("GET")
     * @param Request $request
     */
    public function search(Request $request)
    {
        return $this->render(
            self::TEMPLATE_FOLDER . 'overview.html.twig',
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
                    'login' => $this->generateUrl('login'),
                    'managements_add' => $this->generateUrl('types_managements_add'),
                    'managements_remove' => $this->generateUrl('types_managements_remove'),
                    'help' => $this->generateUrl('page_get', ['slug' => 'search-tips-tricks']),
                ]),
                'data' => json_encode(
                    $this->get('type_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'identifiers' => json_encode(
                    $this->get('identifier_manager')->getPrimaryByTypeJson('type')
                ),
                'managements' => json_encode(
                    $this->isGranted('ROLE_EDITOR_VIEW') ? $this->get('management_manager')->getAllShortJson() : []
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * @Route("/types/search_api", name="types_search_api")
     * @Method("GET")
     * @param Request $request
     */
    public function searchAPI(Request $request)
    {
        $result = $this->get('type_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/types/add", name="type_add")
     * @Method("GET")
     * @param Request $request
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/types/{id}", name="type_get")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * @Route("/typ/{id}", name="type_get_old")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getOld(int $id, Request $request)
    {
        // Let the 404 page handle the not found exception
        $newId = $this->get(static::MANAGER)->getNewId($id);
        return $this->redirectToRoute('type_get', ['id' => $newId], 301);
    }

    /**
     * Get all types that have a dependency on a status
     * (document_status)
     * @Route("/types/statuses/{id}", name="type_deps_by_status")
     * @Method("GET")
     * @param  int    $id status id
     * @param Request $request
     */
    public function getDepsByStatus(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getStatusDependencies');
    }

    /**
     * Get all types that have a dependency on a person
     * (bibrole / factoid)
     * @Route("/types/persons/{id}", name="type_deps_by_person")
     * @Method("GET")
     * @param  int    $id person id
     * @param Request $request
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all types that have a dependency on a metre
     * (poem_metre)
     * @Route("/types/metres/{id}", name="type_deps_by_metre")
     * @Method("GET")
     * @param  int    $id metre id
     * @param Request $request
     */
    public function getDepsByMetre(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getMetreDependencies');
    }

    /**
     * Get all types that have a dependency on a genre
     * (document_genre)
     * @Route("/types/genres/{id}", name="type_deps_by_genre")
     * @Method("GET")
     * @param  int    $id genre id
     * @param Request $request
     */
    public function getDepsByGenre(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getGenreDependencies');
    }

    /**
     * Get all types that have a dependency on a keyword
     * (factoid / document_keyword)
     * @Route("/types/keywords/{id}", name="type_deps_by_keyword")
     * @Method("GET")
     * @param  int    $id keyword id
     * @param Request $request
     */
    public function getDepsByKeyword(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getKeywordDependencies');
    }

    /**
     * Get all types that have a dependency on an acknowledgement
     * (document_acknowledgement)
     * @Route("/types/acknowledgements/{id}", name="type_deps_by_acknowledgement")
     * @Method("GET")
     * @param  int    $id acknowledgement id
     * @param Request $request
     */
    public function getDepsByAcknowledgement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getAcknowledgementDependencies');
    }

    /**
     * Get all types that have a dependency on an occurrence
     * (factoid: based on)
     * @Route("/types/occurrences/{id}", name="type_deps_by_occurrence")
     * @Method("GET")
     * @param  int    $id occurrence id
     * @param Request $request
     */
    public function getDepsByOccurrence(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOccurrenceDependencies');
    }

    /**
     * Get all types that have a dependency on a role
     * (bibrole)
     * @Route("/types/roles/{id}", name="type_deps_by_role")
     * @Method("GET")
     * @param  int    $id role id
     * @param Request $request
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all types that have a dependency on an article
     * (reference)
     * @Route("/types/articles/{id}", name="type_deps_by_article")
     * @Method("GET")
     * @param  int    $id article id
     * @param Request $request
     */
    public function getDepsByArticle(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
    * Get all types that have a dependency on a book
    * (reference)
    * @Route("/types/books/{id}", name="type_deps_by_book")
    * @Method("GET")
    * @param  int    $id book id
    * @param Request $request
    */
    public function getDepsByBook(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all types that have a dependency on a book chapter
     * (reference)
     * @Route("/types/bookchapters/{id}", name="type_deps_by_book_chapter")
     * @Method("GET")
     * @param  int    $id book chapter id
     * @param Request $request
     */
    public function getDepsByBookChapter(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all types that have a dependency on an online source
     * (reference)
     * @Route("/types/onlinesources/{id}", name="type_deps_by_online_source")
     * @Method("GET")
     * @param  int    $id online source id
     * @param Request $request
     */
    public function getDepsByOnlineSource(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all types that have a dependency on a management collection
     * (reference)
     * @Route("/types/managements/{id}", name="type_deps_by_management")
     * @Method("GET")
     * @param  int    $id management id
     * @param Request $request
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @Route("/types", name="type_post")
     * @Method("POST")
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
     * @Route("/types/{id}", name="type_put")
     * @Method("PUT")
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
     * @Route("/types/managements/add", name="types_managements_add")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function addManagements(Request $request)
    {
        return parent::addManagements($request);
    }

    /**
     * @Route("/types/managements/remove", name="types_managements_remove")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function removeManagements(Request $request)
    {
        return parent::removeManagements($request);
    }

    /**
     * @Route("/types/{id}", name="type_delete")
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
     * @Route("/types/{id}/edit", name="type_edit")
     * @Method("GET")
     * @param  int|null $id
     * @param Request $request
     * @return Response
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'urls' => json_encode([
                    'type_get' => $this->generateUrl('type_get', ['id' => $id == null ? 'type_id' : $id]),
                    'type_post' => $this->generateUrl('type_post'),
                    'type_put' => $this->generateUrl('type_put', ['id' => $id == null ? 'type_id' : $id]),
                    'metres_edit' => $this->generateUrl('metres_edit'),
                    'genres_edit' => $this->generateUrl('genres_edit'),
                    'keywords_subject_edit' => $this->generateUrl('subjects_edit'),
                    'keywords_type_edit' => $this->generateUrl('tags_edit'),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'type' => empty($id)
                        ? null
                        : $this->get('type_manager')->getFull($id)->getJson(),
                    'types' => $this->get('type_manager')->getAllMicroShortJson('getId'),
                    'typeRelationTypes' => $this->get('type_relation_type_manager')->getAllShortJson(),
                    'historicalPersons' => $this->get('person_manager')->getAllHistoricalShortJson(),
                    'dbbePersons' => $this->get('person_manager')->getAllDBBEShortJson(),
                    'metres' => $this->get('metre_manager')->getAllShortJson(),
                    'genres' => $this->get('genre_manager')->getAllShortJson(),
                    'subjectKeywords' => $this->get('keyword_manager')->getByTypeShortJson('subject'),
                    'typeKeywords' => $this->get('keyword_manager')->getByTypeShortJson('type'),
                    'articles' => $this->get('article_manager')->getAllMiniShortJson(),
                    'books' => $this->get('book_manager')->getAllMiniShortJson(),
                    'bookChapters' => $this->get('book_chapter_manager')->getAllMiniShortJson(),
                    'onlineSources' => $this->get('online_source_manager')->getAllMiniShortJson(),
                    'referenceTypes' => $this->get('reference_type_manager')->getAllShortJson(),
                    'languages' => $this->get('language_manager')->getAllShortJson(),
                    'acknowledgements' => $this->get('acknowledgement_manager')->getAllShortJson(),
                    'textStatuses' => $this->get('status_manager')->getByTypeShortJson(Status::TYPE_TEXT),
                    'criticalStatuses' => $this->get('status_manager')->getByTypeShortJson(Status::TYPE_CRITICAL),
                    'occurrences' => $this->get('occurrence_manager')->getAllMicroShortJson('getId'),
                    'managements' => $this->get('management_manager')->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $this->get('identifier_manager')->getByTypeJson('type')
                ),
                'roles' => json_encode(
                    $this->get('role_manager')->getByTypeJson('type')
                ),
                'contributorRoles' => json_encode(
                    $this->get('role_manager')->getContributorByTypeJson('type')
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

        if (isset($filters)) {
            // sanitize text_stem
            if (!(isset($filters['text_stem']) && in_array($filters['text_stem'], ['original', 'stemmer']))) {
                $filters['text_stem'] = 'original';
            }
            // sanitize text_fields
            if (!(isset($filters['text_fields']) && in_array($filters['text_fields'], ['text', 'title', 'all']))) {
                $filters['text_fields'] = 'text';
            }
            // sanitize text_combination
            if (!(isset($filters['text_combination']) && in_array($filters['text_combination'], ['any', 'all', 'phrase']))) {
                $filters['text_combination'] = 'all';
            }

            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
