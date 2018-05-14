<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
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
            'AppBundle:Manuscript:overview.html.twig'
        );
    }

    /**
     * @Route("/manuscripts/search_api", name="manuscripts_search_api")
     */
    public function searchManuscriptsAPI(Request $request)
    {
        $params = $request->query->all();

        $es_params = [];

        // Pagination
        if (isset($params['limit']) && is_numeric($params['limit'])) {
            $es_params['limit'] = $params['limit'];
        }
        if (isset($params['page']) && is_numeric($params['page'])) {
            $es_params['page'] = $params['page'];
        }


        // Sorting
        if (isset($params['orderBy'])) {
            if (isset($params['ascending']) && is_numeric($params['ascending'])) {
                $es_params['ascending'] = $params['ascending'];
            }
            if (($params['orderBy']) == 'name') {
                $es_params['orderBy'] = ['name.keyword'];
            } elseif (($params['orderBy']) == 'date') {
                // when sorting in descending order => sort by ceiling, else: sort by floor
                if (isset($params['ascending']) && $params['ascending'] == 0) {
                    $es_params['orderBy'] = ['date_ceiling_year', 'date_floor_year'];
                } else {
                    $es_params['orderBy'] = ['date_floor_year', 'date_ceiling_year'];
                }
            }
        }

        // Filtering
        $filters = [];
        if (isset($params['filters'])) {
            $filters = json_decode($params['filters'], true);
        }

        if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
            $filters['public'] = 1;
        }

        if (isset($filters) && is_array($filters)) {
            $es_params['filters'] = $filters;
        }

        $result = $this->get('manuscript_elastic_service')->searchAndAggregate(
            $es_params
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/manuscripts/add", name="manuscript_add")
     * @Method("GET")
     * @param Request $request
     */
    public function addManuscript(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->editManuscript(null, $request);
    }

    /**
     * @Route("/manuscripts/{id}", name="manuscript_show")
     * @Method("GET")
     * @param  int    $id manuscript id
     * @param Request $request
     */
    public function getManuscript(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR');
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
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByRegion($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new NotFoundHttpException();
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
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByInstitution($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new NotFoundHttpException();
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
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByCollection($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new NotFoundHttpException();
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
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $manuscripts = $this
                ->get('manuscript_manager')
                ->getManuscriptsDependenciesByContent($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($manuscripts));
        } else {
            throw new NotFoundHttpException();
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
            throw new NotFoundHttpException();
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
            throw new NotFoundHttpException();
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
            throw new NotFoundHttpException();
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
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->render(
            'AppBundle:Manuscript:edit.html.twig',
            [
                'id' => $id,
                'manuscript' => empty($id)
                    ? null
                    : json_encode($this->get('manuscript_manager')->getManuscriptById($id)->getJson()),
                'locations' => json_encode(
                    ArrayToJson::arrayToJson($this->get('location_manager')->getLocationsForManuscripts())
                ),
                'contents' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('content_manager')->getAllContentsWithParents())
                ),
                'patrons' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllPatrons())
                ),
                'scribes' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllSCribes())
                ),
                'relatedPersons' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('person_manager')->getAllHistoricalPersons())
                ),
                'origins' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('origin_manager')->getAllOrigins())
                ),
                'books' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllBooks())
                ),
                'articles' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllArticles())
                ),
                'bookChapters' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllBookChapters())
                ),
                'onlineSources' => json_encode(
                    ArrayToJson::arrayToShortJson($this->get('bibliography_manager')->getAllOnlineSources())
                ),
            ]
        );
    }
}
