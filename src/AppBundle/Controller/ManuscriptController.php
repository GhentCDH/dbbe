<?php

namespace AppBundle\Controller;

use AppBundle\Model\Status;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ManuscriptController extends BaseController
{
    const MANAGER = 'manuscript_manager';
    const TEMPLATE_FOLDER = 'AppBundle:Manuscript:';

    /**
     * @Route("/manuscripts", name="manuscripts_base")
     * @Method("GET")
     * @param Request $request
     */
    public function base(Request $request)
    {
        return $this->redirectToRoute('manuscripts_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/manuscripts/search", name="manuscripts_search")
     * @Method("GET")
     * @param Request $request
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
                    'login' => $this->generateUrl('login'),
                    'managements_add' => $this->generateUrl('manuscripts_managements_add'),
                    'managements_remove' => $this->generateUrl('manuscripts_managements_remove'),
                ]),
                'data' => json_encode(
                    $this->get('manuscript_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'identifiers' => json_encode(
                    $this->get('identifier_manager')->getPrimaryByTypeJson('manuscript')
                ),
                'managements' => json_encode(
                    $this->isGranted('ROLE_EDITOR_VIEW') ? $this->get('management_manager')->getAllShortJson() : []
                ),
            ]
        );
    }

    /**
     * @Route("/manuscripts/search_api", name="manuscripts_search_api")
     * @Method("GET")
     * @param Request $request
     */
    public function searchManuscriptsAPI(Request $request)
    {
        $this->throwErrorIfNotJson($request);
        $result = $this->get('manuscript_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
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
                $manuscript = $this->get('manuscript_manager')->getFull($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($manuscript->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $manuscript = $this->get('manuscript_manager')->getFull($id);
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
    public function getDepsByRegion(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRegionDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on an institution
     * (located_at / factoid)
     * @Route("/manuscripts/institutions/{id}", name="manuscript_deps_by_institution")
     * @Method("GET")
     * @param  int    $id institution id
     * @param Request $request
     */
    public function getDepsByInstitution(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getInstitutionDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a collection
     * (located_at / factoid)
     * @Route("/manuscripts/collections/{id}", name="manuscript_deps_by_collection")
     * @Method("GET")
     * @param  int    $id collection id
     * @param Request $request
     */
    public function getDepsByCollection(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getCollectionDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a content
     * (document_genre)
     * @Route("/manuscripts/contents/{id}", name="manuscript_deps_by_content")
     * @Method("GET")
     * @param  int    $id content id
     * @param Request $request
     */
    public function getDepsByContent(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getContentDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a status
     * (document_status)
     * @Route("/manuscripts/statuses/{id}", name="manuscript_deps_by_status")
     * @Method("GET")
     * @param  int    $id status id
     * @param Request $request
     */
    public function getDepsByStatus(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getStatusDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a person
     * (bibrole, factoid)
     * @Route("/manuscripts/persons/{id}", name="manuscript_deps_by_person")
     * @Method("GET")
     * @param  int    $id person id
     * @param Request $request
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a role
     * (bibrole)
     * @Route("/manuscripts/roles/{id}", name="manuscript_deps_by_role")
     * @Method("GET")
     * @param  int    $id role id
     * @param Request $request
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on an article
     * (reference)
     * @Route("/manuscripts/articles/{id}", name="manuscript_deps_by_article")
     * @Method("GET")
     * @param  int    $id article id
     * @param Request $request
     */
    public function getDepsByArticle(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getArticleDependencies');
    }

    /**
    * Get all manuscripts that have a dependency on a book
    * (reference)
    * @Route("/manuscripts/books/{id}", name="manuscript_deps_by_book")
    * @Method("GET")
    * @param  int    $id book id
    * @param Request $request
    */
    public function getDepsByBook(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a book chapter
     * (reference)
     * @Route("/manuscripts/bookchapters/{id}", name="manuscript_deps_by_book_chapter")
     * @Method("GET")
     * @param  int    $id book chapter id
     * @param Request $request
     */
    public function getDepsByBookChapter(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getBookChapterDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on an online source
     * (reference)
     * @Route("/manuscripts/onlinesources/{id}", name="manuscript_deps_by_online_source")
     * @Method("GET")
     * @param  int    $id online source id
     * @param Request $request
     */
    public function getDepsByOnlineSource(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getOnlineSourceDependencies');
    }

    /**
     * Get all manuscripts that have a dependency on a management collection
     * (reference)
     * @Route("/manuscripts/managements/{id}", name="manuscript_deps_by_management")
     * @Method("GET")
     * @param  int    $id management id
     * @param Request $request
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
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
                    ->add(json_decode($request->getContent()));
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
                    ->update($id, json_decode($request->getContent()));
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
     * @Route("/manuscripts/managements/add", name="manuscripts_managements_add")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function addManagements(Request $request)
    {
        return parent::addManagements($request);
    }

    /**
     * @Route("/manuscripts/managements/remove", name="manuscripts_managements_remove")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function removeManagements(Request $request)
    {
        return parent::removeManagements($request);
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
                $this
                    ->get('manuscript_manager')
                    ->delete($id);
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
     * @Method("GET")
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
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'urls' => json_encode([
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => $id == null ? 'manuscript_id' : $id]),
                    'manuscript_post' => $this->generateUrl('manuscript_post'),
                    'manuscript_put' => $this->generateUrl('manuscript_put', ['id' => $id == null ? 'manuscript_id' : $id]),
                    'locations_edit' => $this->generateUrl('locations_edit'),
                    'contents_edit' => $this->generateUrl('contents_edit'),
                    'origins_edit' => $this->generateUrl('origins_edit'),
                    'acknowledgements_edit' => $this->generateUrl('acknowledgements_edit'),
                    'statuses_edit' => $this->generateUrl('statuses_edit'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'manuscript' => empty($id)
                        ? null
                        : $this->get('manuscript_manager')->getFull($id)->getJson(),
                    'locations' => $this->get('location_manager')->getByTypeJson('manuscript'),
                    'contents' => $this->get('content_manager')->getAllShortJson(),
                    'historicalPersons' => $this->get('person_manager')->getAllHistoricalShortJson(),
                    'dbbePersons' => $this->get('person_manager')->getAllDBBEShortJson(),
                    'origins' => $this->get('origin_manager')->getByTypeShortJson('manuscript'),
                    'articles' => $this->get('article_manager')->getAllMiniShortJson(),
                    'books' => $this->get('book_manager')->getAllMiniShortJson(),
                    'bookChapters' => $this->get('book_chapter_manager')->getAllMiniShortJson(),
                    'onlineSources' => $this->get('online_source_manager')->getAllMiniShortJson(),
                    'acknowledgements' => $this->get('acknowledgement_manager')->getAllShortJson(),
                    'statuses' => $this->get('status_manager')->getByTypeShortJson(Status::MANUSCRIPT),
                    'managements' => $this->get('management_manager')->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $this->get('identifier_manager')->getByTypeJson('manuscript')
                ),
                'roles' => json_encode(
                    $this->get('role_manager')->getByTypeJson('manuscript')
                ),
                'contributorRoles' => json_encode(
                    $this->get('role_manager')->getContributorByTypeJson('manuscript')
                ),
                // @codingStandardsIgnoreEnd
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
                    $esParams['orderBy'] = ['date_ceiling_year', 'date_floor_year'];
                } else {
                    $esParams['orderBy'] = ['date_floor_year', 'date_ceiling_year'];
                }
            } else {
                $esParams['orderBy'] = $defaults['orderBy'];
            }
        // Don't set default order if there is a text field filter
        } else if (!(isset($params['filters']['comment']))) {
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
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        if (!empty($filters)) {
            // sanitize date search type
            if (!(isset($filters['date_search_type']) && in_array($filters['date_search_type'], ['exact', 'narrow', 'broad']))) {
                $filters['date_search_type'] = 'exact';
            }

            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
