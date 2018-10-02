<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\Utils\ArrayToJson;

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
                ]),
                'data' => json_encode(
                    $this->get('type_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getPrimaryIdentifiersByType('type'))
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
     * Get all types that have a dependency on a meter
     * (poem_meter)
     * @Route("/types/meters/{id}", name="type_deps_by_meter")
     * @Method("GET")
     * @param  int    $id meter id
     * @param Request $request
     */
    public function getDepsByMeter(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getMeterDependencies');
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
                    'meters_edit' => $this->generateUrl('meters_edit'),
                    'genres_edit' => $this->generateUrl('genres_edit'),
                    'keywords_subject_edit' => $this->generateUrl('keywords_subject_edit'),
                    'keywords_type_edit' => $this->generateUrl('keywords_type_edit'),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'type' => empty($id)
                        ? null
                        : $this->get('type_manager')->getFull($id)->getJson(),
                    'types' => ArrayToJson::arrayToShortJson($this->get('type_manager')->getAllMini('getId')),
                    'typeRelationTypes' => ArrayToJson::arrayToShortJson($this->get('type_relation_type_manager')->getAll()),
                    'historicalPersons' => ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllHistoricalPersons()),
                    'meters' => ArrayToJson::arrayToShortJson($this->get('meter_manager')->getAll()),
                    'genres' => ArrayToJson::arrayToShortJson($this->get('genre_manager')->getAll()),
                    'subjectKeywords' => ArrayToJson::arrayToShortJson($this->get('keyword_manager')->getAllSubjectKeywords()),
                    'typeKeywords' => ArrayToJson::arrayToShortJson($this->get('keyword_manager')->getAllTypeKeywords()),
                    'articles' => ArrayToJson::arrayToShortJson($this->get('article_manager')->getAllMini()),
                    'books' => ArrayToJson::arrayToShortJson($this->get('book_manager')->getAllMini()),
                    'bookChapters' => ArrayToJson::arrayToShortJson($this->get('book_chapter_manager')->getAllMini()),
                    'onlineSources' => ArrayToJson::arrayToShortJson($this->get('online_source_manager')->getAllMini()),
                    'referenceTypes' => ArrayToJson::arrayToShortJson($this->get('reference_type_manager')->getAll()),
                    'acknowledgements' => ArrayToJson::arrayToShortJson($this->get('acknowledgement_manager')->getAll()),
                    'textStatuses' => ArrayToJson::arrayToShortJson($this->get('status_manager')->getAllTypeTextStatuses()),
                    'criticalStatuses' => ArrayToJson::arrayToShortJson($this->get('status_manager')->getAllTypeCriticalStatuses()),
                    'occurrences' => ArrayToJson::arrayToShortJson($this->get('occurrence_manager')->getAllMini('getId')),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getIdentifiersByType('type'))
                ),
                'roles' => json_encode(
                    ArrayToJson::arrayToJson($this->get('role_manager')->getRolesByType('type'))
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
            if (($params['orderBy']) == 'incipit') {
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
            // TODO: detailed sanitation?
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
            // sanitize text_type
            if (!(isset($filters['text_type']) && in_array($filters['text_type'], ['any', 'all', 'phrase']))) {
                $filters['text_type'] = 'any';
            }

            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
