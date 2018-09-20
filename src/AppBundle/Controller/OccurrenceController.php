<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\Utils\ArrayToJson;

class OccurrenceController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'occurrence_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Occurrence:';

    /**
     * @Route("/occurrences/search", name="occurrences_search")
     * @Method("GET")
     * @param Request $request
     */
    public function search(Request $request)
    {
        return $this->render(
            'AppBundle:Occurrence:overview.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'urls' => json_encode([
                    'occurrences_search_api' => $this->generateUrl('occurrences_search_api'),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'occurrence_edit' => $this->generateUrl('occurrence_edit', ['id' => 'occurrence_id']),
                    'occurrence_delete' => $this->generateUrl('occurrence_delete', ['id' => 'occurrence_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                ]),
                'data' => json_encode(
                    $this->get('occurrence_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getPrimaryIdentifiersByType('occurrence'))
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * @Route("/occurrences/search_api", name="occurrences_search_api")
     * @Method("GET")
     * @param Request $request
     */
    public function searchAPI(Request $request)
    {
        $result = $this->get('occurrence_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/occurrences/add", name="occurrence_add")
     * @Method("GET")
     * @param Request $request
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/occurrences/{id}", name="occurrence_get")
     * @Method("GET")
     * @param  int    $id
     * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all occurrences that have a dependency on a manuscript
     * (document_contains)
     * @Route("/occurrences/manuscripts/{id}", name="occurrence_deps_by_manuscript")
     * @Method("GET")
     * @param  int    $id manuscript id
     * @param Request $request
     */
    public function getDepsByManuscript(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManuscriptDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a person
     * (bibrole / factoid)
     * @Route("/occurrences/persons/{id}", name="occurrence_deps_by_person")
     * @Method("GET")
     * @param  int    $id person id
     * @param Request $request
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a meter
     * (poem_meter)
     * @Route("/occurrences/meters/{id}", name="occurrence_deps_by_meter")
     * @Method("GET")
     * @param  int    $id meter id
     * @param Request $request
     */
    public function getDepsByMeter(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getMeterDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a genre
     * (document_genre)
     * @Route("/occurrences/genres/{id}", name="occurrence_deps_by_genre")
     * @Method("GET")
     * @param  int    $id genre id
     * @param Request $request
     */
    public function getDepsByGenre(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getGenreDependencies');
    }

    /**
     * Get all occurrences that have a dependency on a keyword
     * (factoid)
     * @Route("/occurrences/keywords/{id}", name="occurrence_deps_by_keyword")
     * @Method("GET")
     * @param  int    $id keyword id
     * @param Request $request
     */
    public function getDepsByKeyword(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getKeywordDependencies');
    }

    /**
     * @Route("/occurrences", name="occurrence_post")
     * @Method("POST")
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
     * @Route("/occurrences/{id}", name="occurrence_put")
     * @Method("PUT")
     * @param  int    $id occurrence id
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
     * @Route("/occurrences/{id}", name="occurrence_delete")
     * @Method("DELETE")
     * @param  int    $id occurrnence id
     * @param Request $request
     * @return Response
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/occurrences/{id}/edit", name="occurrence_edit")
     * @Method("GET")
     * @param  int|null $id occurrence id
     * @param Request $request
     * @return Response
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Occurrence:edit.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'urls' => json_encode([
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => $id == null ? 'occurrence_id' : $id]),
                    'occurrence_post' => $this->generateUrl('occurrence_post'),
                    'occurrence_put' => $this->generateUrl('occurrence_put', ['id' => $id]),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'verse_variant_get' => $this->generateUrl('verse_variant_get', ['groupId' => 'verse_variant_id']),
                    'verse_search' => $this->generateUrl('verse_search'),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'image_get' => $this->generateUrl('image_get', ['id' => 'image_id']),
                    'image_post' => $this->generateUrl('image_post'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'occurrence' => empty($id)
                        ? null
                        : $this->get('occurrence_manager')->getFull($id)->getJson(),
                    'manuscripts' => ArrayToJson::arrayToShortJson($this->get('manuscript_manager')->getAllMini()),
                    'types' => ArrayToJson::arrayToShortJson($this->get('type_manager')->getAllMini()),
                    'historicalPersons' => ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllHistoricalPersons()),
                    'meters' => ArrayToJson::arrayToShortJson($this->get('meter_manager')->getAll()),
                    'genres' => ArrayToJson::arrayToShortJson($this->get('genre_manager')->getAll()),
                    'keywords' => ArrayToJson::arrayToShortJson($this->get('keyword_manager')->getAll()),
                    'articles' => ArrayToJson::arrayToShortJson($this->get('article_manager')->getAllMini()),
                    'books' => ArrayToJson::arrayToShortJson($this->get('book_manager')->getAllMini()),
                    'bookChapters' => ArrayToJson::arrayToShortJson($this->get('book_chapter_manager')->getAllMini()),
                    'onlineSources' => ArrayToJson::arrayToShortJson($this->get('online_source_manager')->getAllMini()),
                    'referenceTypes' => ArrayToJson::arrayToShortJson($this->get('reference_type_manager')->getAll()),
                    'textStatuses' => ArrayToJson::arrayToShortJson($this->get('status_manager')->getAllOccurrenceTextStatuses()),
                    'recordStatuses' => ArrayToJson::arrayToShortJson($this->get('status_manager')->getAllOccurrenceRecordStatuses()),
                    'dividedStatuses' => ArrayToJson::arrayToShortJson($this->get('status_manager')->getAllOccurrenceDividedStatuses()),
                    'sourceStatuses' => ArrayToJson::arrayToShortJson($this->get('status_manager')->getAllOccurrenceSourceStatuses()),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getIdentifiersByType('occurrence'))
                ),
                'roles' => json_encode(
                    ArrayToJson::arrayToJson($this->get('role_manager')->getRolesByType('occurrence'))
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
            if (isset($params['ascending']) && is_numeric($params['ascending']) && $params['ascending'] === 0) {
                $esParams['ascending'] = $params['ascending'];
            } else {
                $esParams['ascending'] = $defaults['ascending'];
            }
            if (($params['orderBy']) == 'incipit') {
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
