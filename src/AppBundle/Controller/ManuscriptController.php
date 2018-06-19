<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Utils\ArrayToJson;

class ManuscriptController extends Controller
{
    /**
     * @Route("/manuscripts", name="manuscripts_search")
     */
    public function searchManuscripts(Request $request)
    {
        return $this->render(
            'AppBundle:Manuscript:overview.html.twig',
            [
                'urls' => json_encode([
                    'manuscripts_search_api' => $this->generateUrl('manuscripts_search_api'),
                    'occurrence_deps_by_manuscript' => $this->generateUrl('occurrence_deps_by_manuscript', ['id' => 'manuscript_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'manuscript_edit' => $this->generateUrl('manuscript_edit', ['id' => 'manuscript_id']),
                    'manuscript_delete' => $this->generateUrl('manuscript_delete', ['id' => 'manuscript_id']),
                ]),
                'data' => json_encode(
                    $this->get('manuscript_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all())
                    )
                ),
            ]
        );
    }

    /**
     * @Route("/manuscripts/search_api", name="manuscripts_search_api")
     */
    public function searchManuscriptsAPI(Request $request)
    {
        $result = $this->get('manuscript_elastic_service')->searchAndAggregate(
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
     * @Route("/manuscripts/add", name="manuscript_add")
     * @Method("GET")
     * @param Request $request
     */
    public function addManuscript(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->editManuscript(null, $request);
    }

    /**
     * @Route("/manuscripts/{id}", name="manuscript_get")
     * @Method("GET")
     * @param  int    $id manuscript id
     * @param Request $request
     */
    public function getManuscript(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            try {
                $manuscript = $this->get('manuscript_manager')->getManuscriptById($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($manuscript->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $manuscript = $this->get('manuscript_manager')->getManuscriptById($id);
            if (!$manuscript->getPublic()) {
                $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');
            }
            return $this->render(
                'AppBundle:Manuscript:detail.html.twig',
                ['manuscript' => $manuscript]
            );
        }
    }

    /**
     * Get all manuscripts that have a dependency on a region
     * (located_at / factoid)
     * @Route("/manuscripts/regions/{id}", name="manuscript_deps_by_region")
     * @Method("GET")
     * @param  int    $id region id
     * @param Request $request
     */
    public function getManuscriptDepsByRegion(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByRegion($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * Get all manuscripts that have a dependency on an institution
     * (located_at / factoid)
     * @Route("/manuscripts/institutions/{id}", name="manuscript_deps_by_institution")
     * @Method("GET")
     * @param  int    $id institution id
     * @param Request $request
     */
    public function getManuscriptDepsByInstitution(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByInstitution($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * Get all manuscripts that have a dependency on a collection
     * (located_at / factoid)
     * @Route("/manuscripts/collections/{id}", name="manuscript_deps_by_collection")
     * @Method("GET")
     * @param  int    $id collection id
     * @param Request $request
     */
    public function getManuscriptDepsByCollection(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByCollection($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * Get all manuscripts that have a dependency on a content
     * (document_genre)
     * @Route("/manuscripts/contents/{id}", name="manuscript_deps_by_content")
     * @Method("GET")
     * @param  int    $id content id
     * @param Request $request
     */
    public function getManuscriptDepsByContent(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByContent($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * Get all manuscripts that have a dependency on a status
     * (document_status)
     * @Route("/manuscripts/statuses/{id}", name="manuscript_deps_by_status")
     * @Method("GET")
     * @param  int    $id status id
     * @param Request $request
     */
    public function getManuscriptDepsByStatus(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByStatus($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/manuscripts", name="manuscript_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function postManuscript(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $manuscript = $this
                    ->get('manuscript_manager')
                    ->addManuscript(json_decode($request->getContent()));
            } catch (BadRequestHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->addFlash('success', 'Manuscript added successfully.');

            return new JsonResponse($manuscript->getJson());
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/manuscripts/{id}", name="manuscript_put")
     * @Method("PUT")
     * @param  int    $id manuscript id
     * @param Request $request
     * @return JsonResponse
     */
    public function putManuscript(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $manuscript = $this
                    ->get('manuscript_manager')
                    ->updateManuscript($id, json_decode($request->getContent()));
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

            $this->addFlash('success', 'Manuscript data successfully saved.');

            return new JsonResponse($manuscript->getJson());
        } else {
            throw new BadRequestHttpException('Only JSON requests allowed.');
        }
    }

    /**
     * @Route("/manuscripts/{id}", name="manuscript_delete")
     * @Method("DELETE")
     * @param  int    $id manuscript id
     * @param Request $request
     * @return Response
     */
    public function deleteManuscript(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $manuscript = $this
                    ->get('manuscript_manager')
                    ->delManuscript($id);
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
     * @Route("/manuscripts/{id}/edit", name="manuscript_edit")
     * @param  int|null $id manuscript id
     * @param Request $request
     * @return Response
     */
    public function editManuscript(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Manuscript:edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => $id == null ? 'manuscript_id' : $id]),
                    'manuscript_post' => $this->generateUrl('manuscript_post'),
                    'manuscript_put' => $this->generateUrl('manuscript_put', ['id' => $id]),
                    'locations_edit' => $this->generateUrl('locations_edit'),
                    'contents_edit' => $this->generateUrl('contents_edit'),
                    'origins_edit' => $this->generateUrl('origins_edit'),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'manuscript' => empty($id)
                        ? null
                        : $this->get('manuscript_manager')->getManuscriptById($id)->getJson(),
                    'locations' => ArrayToJson::arrayToJson($this->get('location_manager')->getLocationsForManuscripts()),
                    'contents' => ArrayToJson::arrayToShortJson($this->get('content_manager')->getAllContentsWithParents()),
                    'patrons' => ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllPatrons()),
                    'scribes' => ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllSCribes()),
                    'relatedPersons' => ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllHistoricalPersons()),
                    'origins' => ArrayToJson::arrayToShortJson($this->get('origin_manager')->getAllOrigins()),
                    'books' => ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllBooks()),
                    'articles' => ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllArticles()),
                    'bookChapters' => ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllBookChapters()),
                    'onlineSources' => ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllOnlineSources()),
                    'statuses' => ArrayToJson::arrayToShortJson($this->get('status_manager')->getAllManuscriptStatuses()),
                ]),
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
                    $esParams['orderBy'] = ['date_ceiling_year', 'date_floor_year'];
                } else {
                    $esParams['orderBy'] = ['date_floor_year', 'date_ceiling_year'];
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
