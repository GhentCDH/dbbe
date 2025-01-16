<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\JournalManager;
use App\Security\Roles;

class JournalController extends BaseController
{
    public function __construct(JournalManager $journalManager)
    {
        $this->manager = $journalManager;
        $this->templateFolder = 'Journal/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journals', name: 'journals_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getAllMiniShortJson()
        );
    }

    #[Route(path: '/journals/edit', name: 'journals_edit', methods: ['GET'])]
    public function edit()
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder  . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'journals_get' => $this->generateUrl('journals_get'),
                    'journal_issue_deps_by_journal' => $this->generateUrl('journal_issue_deps_by_journal', ['id' => 'journal_id']),
                    'journal_post' => $this->generateUrl('journal_post'),
                    'journal_merge' => $this->generateUrl('journal_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'journal_put' => $this->generateUrl('journal_put', ['id' => 'journal_id']),
                    'journal_delete' => $this->generateUrl('journal_delete', ['id' => 'journal_id']),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                    // @codingStandardsIgnoreEnd
                ]),
                'journals' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/journals/{id}', name: 'journal_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);
            try {
                $object = $this->manager->getFull($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($object->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $object = $this->manager->getFull($id);
            return $this->render(
                $this->templateFolder . 'detail.html.twig',
                [
                    $object::CACHENAME => $object,
                    'issuesArticles' => $this->manager->getIssuesArticles($id),
                ]
            );
        }
    }

    /**
     * @param  int    $primaryId   first journal id (will stay)
     * @param  int    $secondaryId second journal id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journals/{primaryId}/{secondaryId}', name: 'journal_merge', methods: ['PUT'])]
    public function merge(int $primaryId, int $secondaryId, Request $request): JsonResponse
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journals', name: 'journal_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journals/{id}', name: 'journal_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journals/{id}', name: 'journal_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
