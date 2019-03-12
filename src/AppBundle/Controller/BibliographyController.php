<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BibliographyController extends BaseController
{
    /**
     * @Route("/bibliographies", name="bibliographies_base")
     * @Method("GET")
     * @param Request $request
     */
    public function base(Request $request)
    {
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
    * @Route("/bibliographies/search", name="bibliographies_search")
    * @Method("GET")
    * @param Request $request
    */
    public function searchBibliographies(Request $request)
    {
        return $this->render(
            'AppBundle:Bibliography:overview.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'urls' => json_encode([
                    'bibliographies_search_api' => $this->generateUrl('bibliographies_search_api'),
                    'manuscript_deps_by_article' => $this->generateUrl('manuscript_deps_by_article', ['id' => 'article_id']),
                    'manuscript_deps_by_book' => $this->generateUrl('manuscript_deps_by_book', ['id' => 'book_id']),
                    'manuscript_deps_by_book_chapter' => $this->generateUrl('manuscript_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'manuscript_deps_by_online_source' => $this->generateUrl('manuscript_deps_by_online_source', ['id' => 'online_source_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'occurrence_deps_by_article' => $this->generateUrl('occurrence_deps_by_article', ['id' => 'article_id']),
                    'occurrence_deps_by_book' => $this->generateUrl('occurrence_deps_by_book', ['id' => 'book_id']),
                    'occurrence_deps_by_book_chapter' => $this->generateUrl('occurrence_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'occurrence_deps_by_online_source' => $this->generateUrl('occurrence_deps_by_online_source', ['id' => 'online_source_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_article' => $this->generateUrl('type_deps_by_article', ['id' => 'article_id']),
                    'type_deps_by_book' => $this->generateUrl('type_deps_by_book', ['id' => 'book_id']),
                    'type_deps_by_book_chapter' => $this->generateUrl('type_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'type_deps_by_online_source' => $this->generateUrl('type_deps_by_online_source', ['id' => 'online_source_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'person_deps_by_article' => $this->generateUrl('person_deps_by_article', ['id' => 'article_id']),
                    'person_deps_by_book' => $this->generateUrl('person_deps_by_book', ['id' => 'book_id']),
                    'person_deps_by_book_chapter' => $this->generateUrl('person_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'person_deps_by_online_source' => $this->generateUrl('person_deps_by_online_source', ['id' => 'online_source_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'article_edit' => $this->generateUrl('article_edit', ['id' => 'article_id']),
                    'article_delete' => $this->generateUrl('article_delete', ['id' => 'article_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_edit' => $this->generateUrl('book_edit', ['id' => 'book_id']),
                    'book_delete' => $this->generateUrl('book_delete', ['id' => 'book_id']),
                    'book_chapter_get' => $this->generateUrl('book_chapter_get', ['id' => 'book_chapter_id']),
                    'book_chapter_edit' => $this->generateUrl('book_chapter_edit', ['id' => 'book_chapter_id']),
                    'book_chapter_delete' => $this->generateUrl('book_chapter_delete', ['id' => 'book_chapter_id']),
                    'online_source_get' => $this->generateUrl('online_source_get', ['id' => 'online_source_id']),
                    'online_source_edit' => $this->generateUrl('online_source_edit', ['id' => 'online_source_id']),
                    'online_source_delete' => $this->generateUrl('online_source_delete', ['id' => 'online_source_id']),
                    'journal_get' => $this->generateUrl('journal_get', ['id' => 'journal_id']),
                    'managements_add' => $this->generateUrl('bibliographies_managements_add'),
                    'managements_remove' => $this->generateUrl('bibliographies_managements_remove'),
                ]),
                'data' => json_encode(
                    $this->get('bibliography_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'identifiers' => json_encode($this->get('identifier_manager')->getPrimaryByTypeJson('book')),
                'managements' => json_encode(
                    $this->isGranted('ROLE_EDITOR_VIEW') ? $this->get('management_manager')->getAllShortJson() : []
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * @Route("/bibliographies/search_api", name="bibliographies_search_api")
     * @Method("GET")
     * @param Request $request
     */
    public function searchBibliographiesAPI(Request $request)
    {
        $result = $this->get('bibliography_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/bibliographies/managements/add", name="bibliographies_managements_add")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function addManagements(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        $content = json_decode($request->getContent());

        try {
            $this
                ->get('article_manager')
                ->addManagements($content);
            $this
                ->get('book_manager')
                ->addManagements($content);
            $this
                ->get('book_chapter_manager')
                ->addManagements($content);
            $this
                ->get('online_source_manager')
                ->addManagements($content);
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

        // return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        return new JsonResponse(null);
    }

    /**
     * @Route("/bibliographies/managements/remove", name="bibliographies_managements_remove")
     * @Method("PUT")
     * @param Request $request
     * @return JsonResponse
     */
    public function removeManagements(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $this->throwErrorIfNotJson($request);

        $content = json_decode($request->getContent());

        try {
            $this
                ->get('article_manager')
                ->removeManagements($content);
            $this
                ->get('book_manager')
                ->removeManagements($content);
            $this
                ->get('book_chapter_manager')
                ->removeManagements($content);
            $this
                ->get('online_source_manager')
                ->removeManagements($content);
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

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function sanitize(array $params): array
    {
        $defaults = [
            'limit' => 25,
            'page' => 1,
            'ascending' => 1,
            'orderBy' => ['title.keyword'],
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
            if (($params['orderBy']) == 'title') {
                $esParams['orderBy'] = ['title.keyword'];
            } else {
                $esParams['orderBy'] = $defaults['orderBy'];
            }
        // Don't set default order if there is a text field filter
        } else if (!(isset($params['filters']['title']) || isset($params['filters']['comment']))) {
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

        if (isset($filters) && is_array($filters)) {
            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
