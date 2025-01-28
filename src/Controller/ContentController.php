<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\ContentManager;
use App\ObjectStorage\PersonManager;
use App\Security\Roles;

class ContentController extends BaseController
{
    public function __construct(ContentManager $contentManager)
    {
        $this->manager = $contentManager;
        $this->templateFolder = 'Content/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/contents', name: 'contents_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        return parent::getAll($request);
    }

    /**
     * Get all contents that have a dependency on a content
     * (genre -> idparentgenre)
     * @param int $id The content id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/contents/contents/{id}', name: 'content_deps_by_content', methods: ['GET'])]
    public function getDepsByContent(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getContentDependencies');
    }

    /**
     * @param PersonManager $personManager
     * @return Response
     */
    #[Route(path: '/contents/edit', name: 'contents_edit', methods: ['GET'])]
    public function edit(PersonManager $personManager): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            'Content/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'contents_get' => $this->generateUrl('contents_get'),
                    'manuscript_deps_by_content' => $this->generateUrl('manuscript_deps_by_content', ['id' => 'content_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'content_deps_by_content' => $this->generateUrl('content_deps_by_content', ['id' => 'content_id']),
                    'content_post' => $this->generateUrl('content_post'),
                    'content_merge' => $this->generateUrl('content_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'content_put' => $this->generateUrl('content_put', ['id' => 'content_id']),
                    'content_delete' => $this->generateUrl('content_delete', ['id' => 'content_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'contents' => json_encode($this->manager->getAllJson()),
                'persons' => json_encode($personManager->getAllHistoricalShortJson()),
            ]
        );
    }

    /**
     * Get all contents that have a dependency on a person
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/contents/persons/{id}', name: 'content_deps_by_person', methods: ['GET'])]
    public function getDepsByPerson(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/contents', name: 'content_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $primaryId   first content id (will stay)
     * @param  int    $secondaryId second content id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/contents/{primaryId}/{secondaryId}', name: 'content_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param  int    $id content id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/contents/{id}', name: 'content_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id content id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/contents/{id}', name: 'content_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
