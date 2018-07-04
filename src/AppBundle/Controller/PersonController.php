<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class PersonController extends Controller
{
    /**
     * @Route("/persons/search", name="persons_search")
     * @Method("GET")
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
                    'person_merge' => $this->generateUrl('person_merge', ['primary' => 'primary_id', 'secondary' => 'secondary_id']),
                    'person_delete' => $this->generateUrl('person_delete', ['id' => 'person_id']),
                    'persons_get' => $this->generateUrl('persons_get'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode(
                    $this->get('person_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'persons' => json_encode(
                    $this->isGranted('ROLE_EDITOR_VIEW') ? ArrayToJson::arrayToJson($this->get('person_manager')->getAllPersons()) : []
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
     */
    public function searchPersonsAPI(Request $request)
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
     */
    public function getPersons(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson(
                    $this->get('person_manager')->getAllPersons()
                )
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
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
     * @Method("GET")
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
     * Get all persons that have a dependency on an occupation
     * (person_occupation)
     * @Route("/persons/occupations/{id}", name="person_deps_by_occupation")
     * @Method("GET")
     * @param  int    $id occupation id
     * @param Request $request
     */
    public function getPersonDepsByOccupation(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $persons = $this
                ->get('person_manager')
                ->getPersonsDependenciesByOccupation($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($persons));
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/persons", name="person_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postPerson(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $person = $this
                    ->get('person_manager')
                    ->addPerson(json_decode($request->getContent()));
            } catch (BadRequestHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->addFlash('success', 'Person added successfully.');

            return new JsonResponse($person->getJson());
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/persons/{primary}/{secondary}", name="person_merge")
     * @Method("PUT")
     * @param  int    $primary first person id (will stay)
     * @param  int    $secondary second person id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function mergePersons(int $primary, int $secondary, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        try {
            $person = $this
                ->get('person_manager')
                ->mergePersons($primary, $secondary);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }
        return new JsonResponse($person->getJson());
    }

    /**
     * @Route("/persons/{id}", name="person_put")
     * @Method("PUT")
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function putPerson(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $person = $this
                    ->get('person_manager')
                    ->updatePerson($id, json_decode($request->getContent()));
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            } catch (BadRequestHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->addFlash('success', 'Person data successfully saved.');

            return new JsonResponse($person->getJson());
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
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
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $person = $this
                    ->get('person_manager')
                    ->delPerson($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            } catch (BadRequestHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return new Response(null, 204);
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/persons/{id}/edit", name="person_edit")
     * @Method("GET")
     * @param  int|null $id person id
     * @param Request $request
     * @return Response
     */
    public function editPerson(int $id = null, Request $request)
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
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'person' => empty($id)
                        ? null
                        : $this->get('person_manager')->getPersonById($id)->getJson(),
                    'types' => ArrayToJson::arrayToShortJson($this->get('occupation_manager')->getAllTypes()),
                    'functions' => ArrayToJson::arrayToShortJson($this->get('occupation_manager')->getAllFunctions()),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getIdentifiersByType('person'))
                ),
            ]
        );
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
