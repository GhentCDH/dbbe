<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PersonController extends Controller
{
    /**
     * @Route("/persons", name="persons_search")
     * @param Request $request
     */
    public function searchPersons(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');

        return $this->render(
            'AppBundle:Person:overview.html.twig',
            [
                'urls' => json_encode([
                    'persons_search_api' => $this->generateUrl('persons_search_api'),
                    'manuscript_deps_by_person' => $this->generateUrl('manuscript_deps_by_person', ['id' => 'person_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'occurrence_deps_by_person' => $this->generateUrl('occurrence_deps_by_person', ['id' => 'person_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'person_edit' => $this->generateUrl('person_edit', ['id' => 'person_id']),
                    'person_delete' => $this->generateUrl('person_delete', ['id' => 'person_id']),
                ]),
                'data' => json_encode(
                    $this->get('person_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all())
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/persons/search_api", name="persons_search_api")
     * @param Request $request
     */
    public function searchPersonsAPI(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');

        $result = $this->get('person_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all())
        );

        // Remove non public fields if no access rights
        if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
            unset($result['aggregation']['public']);
            foreach ($result['data'] as $key => $value) {
                unset($result['data'][$key]['public']);
            }
        }

        return new JsonResponse($result);
    }

    /**
     * @Route("/persons/add", name="person_add")
     * @Method("GET")
     * @param Request $request
     */
    public function addPerson(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->editPerson(null, $request);
    }

    /**
     * @Route("/persons/{id}", name="person_get")
     * @param  int    $id person id
     * @param Request $request
     */
    public function getPerson(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            try {
                $person = $this->get('person_manager')->getPersonById($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($person->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $person = $this->get('person_manager')->getPersonById($id);
            if (!$person->getPublic()) {
                $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');
            }
            return $this->render(
                'AppBundle:Person:detail.html.twig',
                ['person' => $person]
            );
        }
    }

    /**
     * @Route("/persons/{id}", name="person_delete")
     * @Method("DELETE")
     * @param  int    $id person id
     * @param Request $request
     * @return Response
     */
    public function deletePerson(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * @Route("/persons/{id}/edit", name="person_edit")
     * @param  int|null $id person id
     * @param Request $request
     * @return Response
     */
    public function editPerson(int $id = null, Request $request)
    {
        throw new \Exception('Not implemented');
    }

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
                    $esParams['orderBy'] = ['death_date_ceiling_year', 'death_date_floor_year', 'born_date_ceiling_year', 'born_date_floor_year'];
                } else {
                    $esParams['orderBy'] = ['born_date_floor_year', 'born_date_ceiling_year', 'death_date_floor_year', 'death_date_ceiling_year'];
                }
            } else {
                $esParams['orderBy'] = $defaults['orderBy'];
            }
        } else {
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
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        if (isset($filters) && is_array($filters)) {
            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
