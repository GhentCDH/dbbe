<?php

namespace App\Controller;

use App\ObjectStorage\ArticleManager;
use App\ObjectStorage\BibVariaManager;
use App\ObjectStorage\BlogManager;
use App\ObjectStorage\BlogPostManager;
use App\ObjectStorage\BookChapterManager;
use App\ObjectStorage\BookClusterManager;
use App\ObjectStorage\BookManager;
use App\ObjectStorage\BookSeriesManager;
use App\ObjectStorage\JournalManager;
use App\ObjectStorage\OnlineSourceManager;
use App\ObjectStorage\PhdManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ElasticSearchService\ElasticBibliographyService;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\Security\Roles;

class BibliographyController extends BaseController
{
    /**
     * @Route("/bibliographies", name="bibliographies_base", methods={"GET"})
     * @param Request $request
     * @return RedirectResponse
     */
    public function base(Request $request)
    {
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/bibliographies/search", name="bibliographies_search", methods={"GET"})
     * @param Request $request
     * @param ElasticBibliographyService $elasticBibliographyService
     * @param IdentifierManager $identifierManager
     * @param ManagementManager $managementManager
     * @return Response
     */
    public function search(
        Request $request,
        ElasticBibliographyService $elasticBibliographyService,
        IdentifierManager $identifierManager,
        ManagementManager $managementManager
    ) {
        return $this->render(
            'Bibliography/overview.html.twig',
            [
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'urls' => json_encode([
                    'bibliographies_search_api' => $this->generateUrl('bibliographies_search_api'),
                    'manuscript_deps_by_article' => $this->generateUrl('manuscript_deps_by_article', ['id' => 'article_id']),
                    'manuscript_deps_by_blog_post' => $this->generateUrl('manuscript_deps_by_blog_post', ['id' => 'blog_post_id']),
                    'manuscript_deps_by_book' => $this->generateUrl('manuscript_deps_by_book', ['id' => 'book_id']),
                    'manuscript_deps_by_book_chapter' => $this->generateUrl('manuscript_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'manuscript_deps_by_online_source' => $this->generateUrl('manuscript_deps_by_online_source', ['id' => 'online_source_id']),
                    'manuscript_deps_by_phd' => $this->generateUrl('manuscript_deps_by_phd', ['id' => 'phd_id']),
                    'manuscript_deps_by_bib_varia' => $this->generateUrl('manuscript_deps_by_bib_varia', ['id' => 'bib_varia_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'occurrence_deps_by_article' => $this->generateUrl('occurrence_deps_by_article', ['id' => 'article_id']),
                    'occurrence_deps_by_blog_post' => $this->generateUrl('occurrence_deps_by_blog_post', ['id' => 'blog_post_id']),
                    'occurrence_deps_by_book' => $this->generateUrl('occurrence_deps_by_book', ['id' => 'book_id']),
                    'occurrence_deps_by_book_chapter' => $this->generateUrl('occurrence_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'occurrence_deps_by_online_source' => $this->generateUrl('occurrence_deps_by_online_source', ['id' => 'online_source_id']),
                    'occurrence_deps_by_phd' => $this->generateUrl('occurrence_deps_by_phd', ['id' => 'phd_id']),
                    'occurrence_deps_by_bib_varia' => $this->generateUrl('occurrence_deps_by_bib_varia', ['id' => 'bib_varia_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_article' => $this->generateUrl('type_deps_by_article', ['id' => 'article_id']),
                    'type_deps_by_blog_post' => $this->generateUrl('type_deps_by_blog_post', ['id' => 'blog_post_id']),
                    'type_deps_by_book' => $this->generateUrl('type_deps_by_book', ['id' => 'book_id']),
                    'type_deps_by_book_chapter' => $this->generateUrl('type_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'type_deps_by_online_source' => $this->generateUrl('type_deps_by_online_source', ['id' => 'online_source_id']),
                    'type_deps_by_phd' => $this->generateUrl('type_deps_by_phd', ['id' => 'phd_id']),
                    'type_deps_by_bib_varia' => $this->generateUrl('type_deps_by_bib_varia', ['id' => 'bib_varia_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'person_deps_by_article' => $this->generateUrl('person_deps_by_article', ['id' => 'article_id']),
                    'person_deps_by_blog_post' => $this->generateUrl('person_deps_by_blog_post', ['id' => 'blog_post_id']),
                    'person_deps_by_book' => $this->generateUrl('person_deps_by_book', ['id' => 'book_id']),
                    'person_deps_by_book_chapter' => $this->generateUrl('person_deps_by_book_chapter', ['id' => 'book_chapter_id']),
                    'person_deps_by_online_source' => $this->generateUrl('person_deps_by_online_source', ['id' => 'online_source_id']),
                    'person_deps_by_phd' => $this->generateUrl('person_deps_by_phd', ['id' => 'phd_id']),
                    'person_deps_by_bib_varia' => $this->generateUrl('person_deps_by_bib_varia', ['id' => 'bib_varia_id']),
                    'blog_post_deps_by_blog' => $this->generateUrl('blog_post_deps_by_blog', ['id' => 'blog_id']),
                    'book_chapter_deps_by_book' => $this->generateUrl('book_chapter_deps_by_book', ['id' => 'book_id']),
                    'person_get' => $this->generateUrl('person_get', ['id' => 'person_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'article_edit' => $this->generateUrl('article_edit', ['id' => 'article_id']),
                    'article_delete' => $this->generateUrl('article_delete', ['id' => 'article_id']),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_edit' => $this->generateUrl('book_edit', ['id' => 'book_id']),
                    'book_merge' => $this->generateUrl('book_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'book_delete' => $this->generateUrl('book_delete', ['id' => 'book_id']),
                    'book_chapter_get' => $this->generateUrl('book_chapter_get', ['id' => 'book_chapter_id']),
                    'book_chapter_edit' => $this->generateUrl('book_chapter_edit', ['id' => 'book_chapter_id']),
                    'book_chapter_delete' => $this->generateUrl('book_chapter_delete', ['id' => 'book_chapter_id']),
                    'book_cluster_get' => $this->generateUrl('book_cluster_get', ['id' => 'book_cluster_id']),
                    'book_clusters_edit' => $this->generateUrl('book_clusters_edit', ['id' => 'book_cluster_id']),
                    'book_series_get' => $this->generateUrl('book_series_get', ['id' => 'book_series_id']),
                    'book_seriess_edit' => $this->generateUrl('book_seriess_edit', ['id' => 'book_series_id']),
                    'online_source_get' => $this->generateUrl('online_source_get', ['id' => 'online_source_id']),
                    'online_source_edit' => $this->generateUrl('online_source_edit', ['id' => 'online_source_id']),
                    'online_source_delete' => $this->generateUrl('online_source_delete', ['id' => 'online_source_id']),
                    'phd_get' => $this->generateUrl('phd_get', ['id' => 'phd_id']),
                    'phd_edit' => $this->generateUrl('phd_edit', ['id' => 'phd_id']),
                    'phd_delete' => $this->generateUrl('phd_delete', ['id' => 'phd_id']),
                    'bib_varia_get' => $this->generateUrl('bib_varia_get', ['id' => 'bib_varia_id']),
                    'bib_varia_edit' => $this->generateUrl('bib_varia_edit', ['id' => 'bib_varia_id']),
                    'bib_varia_delete' => $this->generateUrl('bib_varia_delete', ['id' => 'bib_varia_id']),
                    'blog_get' => $this->generateUrl('blog_get', ['id' => 'blog_id']),
                    'blog_edit' => $this->generateUrl('blog_edit', ['id' => 'blog_id']),
                    'blog_delete' => $this->generateUrl('blog_delete', ['id' => 'blog_id']),
                    'blog_post_get' => $this->generateUrl('blog_post_get', ['id' => 'blog_post_id']),
                    'blog_post_edit' => $this->generateUrl('blog_post_edit', ['id' => 'blog_post_id']),
                    'blog_post_delete' => $this->generateUrl('blog_post_delete', ['id' => 'blog_post_id']),
                    'journal_get' => $this->generateUrl('journal_get', ['id' => 'journal_id']),
                    'journals_edit' => $this->generateUrl('journals_edit', ['id' => 'journal_id']),
                    'journal_merge' => $this->generateUrl('journal_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'books_get' => $this->generateUrl('books_get'),
                    'journals_get' => $this->generateUrl('journals_get'),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    'managements_add' => $this->generateUrl('bibliographies_managements_add'),
                    'managements_remove' => $this->generateUrl('bibliographies_managements_remove'),
                ]),
                'data' => json_encode(
                    $elasticBibliographyService->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
                    )
                ),
                'identifiers' => json_encode($identifierManager->getPrimaryByTypeJson('book')),
                'managements' => json_encode(
                    $this->isGranted(Roles::ROLE_EDITOR_VIEW) ? $managementManager->getAllShortJson() : []
                ),
                // @codingStandardsIgnoreEnd
            ]
        );
    }

    /**
     * @Route("/bibliographies/search_api", name="bibliographies_search_api", methods={"GET"})
     * @param Request $request
     * @param ElasticBibliographyService $elasticBibliographyService
     * @return JsonResponse
     */
    public function searchAPI(
        Request $request,
        ElasticBibliographyService $elasticBibliographyService
    ) {
        $this->throwErrorIfNotJson($request);
        $result = $elasticBibliographyService->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted(Roles::ROLE_VIEW_INTERNAL)
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/bibliographies/managements/add", name="bibliographies_managements_add", methods={"PUT"})
     * @param Request $request
     * @param ArticleManager $articleManager
     * @param BookManager $bookManager
     * @param BookChapterManager $bookChapterManager
     * @param OnlineSourceManager $onlineSourceManager
     * @param JournalManager $journalManager
     * @param BookClusterManager $bookClusterManager
     * @param BookSeriesManager $bookSeriesManager
     * @param BlogManager $blogManager
     * @param BlogPostManager $blogPostManager
     * @param PhdManager $phdManager
     * @param BibVariaManager $bibVariaManager
     * @return JsonResponse
     * @throws \Exception
     */
    public function addBibliographyManagements(
        Request $request,
        ArticleManager $articleManager,
        BookManager $bookManager,
        BookChapterManager $bookChapterManager,
        OnlineSourceManager $onlineSourceManager,
        JournalManager $journalManager,
        BookClusterManager $bookClusterManager,
        BookSeriesManager $bookSeriesManager,
        BlogManager $blogManager,
        BlogPostManager $blogPostManager,
        PhdManager $phdManager,
        BibVariaManager $bibVariaManager
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);
        $this->throwErrorIfNotJson($request);

        $content = json_decode($request->getContent());

        try {
            $articleManager->addManagements($content);
            $bookManager->addManagements($content);
            $bookChapterManager->addManagements($content);
            $onlineSourceManager->addManagements($content);
            $journalManager->addManagements($content);
            $bookClusterManager->addManagements($content);
            $bookSeriesManager->addManagements($content);
            $blogManager->addManagements($content);
            $blogPostManager->addManagements($content);
            $phdManager->addManagements($content);
            $bibVariaManager->addManagements($content);
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
     * @Route("/bibliographies/managements/remove", name="bibliographies_managements_remove", methods={"PUT"})
     * @param Request $request
     * @param ArticleManager $articleManager
     * @param BookManager $bookManager
     * @param BookChapterManager $bookChapterManager
     * @param OnlineSourceManager $onlineSourceManager
     * @param JournalManager $journalManager
     * @param BookClusterManager $bookClusterManager
     * @param BookSeriesManager $bookSeriesManager
     * @param BlogManager $blogManager
     * @param BlogPostManager $blogPostManager
     * @param PhdManager $phdManager
     * @param BibVariaManager $bibVariaManager
     * @return JsonResponse
     * @throws \Exception
     */
    public function removeBibliographyManagements(
        Request $request,
        ArticleManager $articleManager,
        BookManager $bookManager,
        BookChapterManager $bookChapterManager,
        OnlineSourceManager $onlineSourceManager,
        JournalManager $journalManager,
        BookClusterManager $bookClusterManager,
        BookSeriesManager $bookSeriesManager,
        BlogManager $blogManager,
        BlogPostManager $blogPostManager,
        PhdManager $phdManager,
        BibVariaManager $bibVariaManager
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);
        $this->throwErrorIfNotJson($request);

        $content = json_decode($request->getContent());

        try {
            $articleManager->removeManagements($content);
            $bookManager->removeManagements($content);
            $bookChapterManager->removeManagements($content);
            $onlineSourceManager->removeManagements($content);
            $journalManager->removeManagements($content);
            $bookClusterManager->removeManagements($content);
            $bookSeriesManager->removeManagements($content);
            $blogManager->removeManagements($content);
            $blogPostManager->removeManagements($content);
            $phdManager->removeManagements($content);
            $bibVariaManager->removeManagements($content);
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
            'orderBy' => ['title_sort_key.keyword'],
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
                $esParams['orderBy'] = ['title_sort_key.keyword'];
            } elseif (($params['orderBy']) == 'type') {
                $esParams['orderBy'] = ['type.name.keyword'];
            } elseif (($params['orderBy']) == 'author') {
                $esParams['orderBy'] = [
                    $this->isGranted(Roles::ROLE_VIEW_INTERNAL) ? 'author_last_name.keyword' : 'author_last_name_public.keyword'
                ];
            } else {
                $esParams['orderBy'] = $defaults['orderBy'];
            }
        // Don't set default order if there is a text field filter
        } elseif (!(isset($params['filters']['title']) || isset($params['filters']['comment']))) {
            $esParams['orderBy'] = $defaults['orderBy'];
        }

        // Filtering
        $filters = [];
        if (isset($params['filters']) && is_array($params['filters'])) {
            // TODO detailed sanitization
            $filters = $params['filters'];
        }

        // limit results to public if no access rights
        if (!$this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
            $filters['public'] = '1';
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted(Roles::ROLE_VIEW_INTERNAL)) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        // default for title type
        if (isset($filters['title']) && !isset($filters['title_type'])) {
            $filters['title_type'] = 'any';
        }

        if (!empty($filters)) {
            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
