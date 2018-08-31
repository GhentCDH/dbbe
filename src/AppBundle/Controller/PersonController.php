<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\Utils\ArrayToJson;

class PersonController extends BasicController
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
     * @Route("/persons/search", name="persons_search")
     * @Method("GET")
     * @param Request $request
     * @return Response
     */
    public function search(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');

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
                    'article_deps_by_person' => $this->generateUrl('article_deps_by_person', ['id' => 'person_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'book_deps_by_person' => $this->generateUrl('book_deps_by_person', ['id' => 'person_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_chapter_deps_by_person' => $this->generateUrl('book_chapter_deps_by_person', ['id' => 'person_id']),
                    'book_chapter_get' => $this->generateUrl('book_chapter_get', ['id' => 'book_chapter_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'person_edit' => $this->generateUrl('person_edit', ['id' => 'person_id']),
                    'person_merge' => $this->generateUrl('person_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'person_delete' => $this->generateUrl('person_delete', ['id' => 'person_id']),
                    'persons_get' => $this->generateUrl('persons_get'),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'data' => json_encode(
                    $this->get('person_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'persons' => json_encode(
                    $this->isGranted('ROLE_EDITOR_VIEW')
                        ? ArrayToJson::arrayToJson($this->get('person_manager')->getAllShort())
                        : []
                ),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getPrimaryIdentifiersByType('person'))
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
        $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');

        $result = $this->get('person_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/persons", name="persons_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
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
     * (person_office)
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
                    'offices_edit' => $this->generateUrl('offices_edit'),
                    'origins_edit' => $this->generateUrl('origins_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'person' =>
                        empty($id)
                        ? null
                        : $this->get('person_manager')->getFull($id)->getJson(),
                    'offices' => ArrayToJson::arrayToShortJson($this->get('office_manager')->getAll()),
                    'origins' => ArrayToJson::arrayToShortJson($this->get('origin_manager')->getOriginsForPersons()),
                    'articles' => ArrayToJson::arrayToShortJson($this->get('article_manager')->getAllMini()),
                    'books' => ArrayToJson::arrayToShortJson($this->get('book_manager')->getAllMini()),
                    'bookChapters' => ArrayToJson::arrayToShortJson($this->get('book_chapter_manager')->getAllMini()),
                    'onlineSources' => ArrayToJson::arrayToShortJson($this->get('online_source_manager')->getAllMini()),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getIdentifiersByType('person'))
                ),
            ]
        );
    }

    /**
     * Sanitize data from request string
     * @param  array $params [description]
     * @return array         [description]
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
            if (isset($params['ascending']) && is_numeric($params['ascending'])) {
                $esParams['ascending'] = $params['ascending'];
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
            $identifiers = array_keys($this->get('identifier_manager')->getPrimaryIdentifiersByType('person'));

            foreach (array_keys($params['filters']) as $key) {
                switch ($key) {
                    case 'name':
                    case 'self_designation':
                    case 'comment':
                        if (is_string($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
                        }
                        break;
                    case 'historical':
                    case 'modern':
                    case 'year_from':
                    case 'year_to':
                    case 'role':
                    case 'office':
                    case 'origin':
                    case 'public':
                        if (is_numeric($params['filters'][$key])) {
                            $filters[$key] = $params['filters'][$key];
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
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        $esParams['filters'] = $filters;

        return $esParams;
    }
}
