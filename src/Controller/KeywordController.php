<?php

namespace App\Controller;

use Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\KeywordManager;
use App\ObjectStorage\PersonManager;
use App\Security\Roles;

class KeywordController extends BaseController
{
    public function __construct(KeywordManager $keywordManager)
    {
        $this->manager = $keywordManager;
        $this->templateFolder = 'Keyword/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/keywords', name: 'subjects_get', methods: ['GET'])]
    public function getAllSubjects(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getByTypeJson('subject')
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/tags', name: 'tags_get', methods: ['GET'])]
    public function getAllTags(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getByTypeJson('type')
        );
    }

    /**
     * @param PersonManager $personManager
     * @return Response
     */
    #[Route(path: '/keywords/edit', name: 'subjects_edit', methods: ['GET'])]
    public function subjectEdit(PersonManager $personManager): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'keywords_get' => $this->generateUrl('subjects_get'),
                    'occurrence_deps_by_keyword' => $this->generateUrl('occurrence_deps_by_keyword', ['id' => 'keyword_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_keyword' => $this->generateUrl('type_deps_by_keyword', ['id' => 'keyword_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'keyword_post' => $this->generateUrl('keyword_post'),
                    'keyword_put' => $this->generateUrl('keyword_put', ['id' => 'keyword_id']),
                    'keyword_migrate_person' => $this->generateUrl('keyword_migrate_person', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'keyword_delete' => $this->generateUrl('keyword_delete', ['id' => 'keyword_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'keywords' => json_encode($this->manager->getByTypeJson('subject')),
                'persons' => json_encode($personManager->getAllHistoricalShortJson()),
                'isSubject' => json_encode(true),
            ]
        );
    }

    /**
     * @return Response
     */
    #[Route(path: '/tags/edit', name: 'tags_edit', methods: ['GET'])]
    public function tagEdit(): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'keywords_get' => $this->generateUrl('tags_get'),
                    'occurrence_deps_by_keyword' => $this->generateUrl('occurrence_deps_by_keyword', ['id' => 'keyword_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_keyword' => $this->generateUrl('type_deps_by_keyword', ['id' => 'keyword_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'keyword_post' => $this->generateUrl('keyword_post'),
                    'keyword_put' => $this->generateUrl('keyword_put', ['id' => 'keyword_id']),
                    'keyword_delete' => $this->generateUrl('keyword_delete', ['id' => 'keyword_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'keywords' => json_encode($this->manager->getByTypeJson('type')),
                'persons' => json_encode([]),
                'isSubject' => json_encode(false),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/keywords', name: 'keyword_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/keywords/{id}', name: 'keyword_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param int $primaryId keyword id (will be deleted)
     * @param int $secondaryId person id (will stay)
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    #[Route(path: '/keywords/{primaryId}/persons/{secondaryId}', name: 'keyword_migrate_person', methods: ['PUT'])]
    public function migrate(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR);
        $this->throwErrorIfNotJson($request);

        try {
            $this->manager->migratePerson($primaryId, $secondaryId);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(
                ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/keywords/{id}', name: 'keyword_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
