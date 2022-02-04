<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\JournalManager;

class JournalController extends BaseController
{
    public function __construct(JournalManager $journalManager)
    {
        $this->manager = $journalManager;
        $this->templateFolder = 'Journal/';
    }

    /**
     * @Route("/journals", name="journals_get", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->manager->getAllMiniShortJson()
        );
    }

    /**
     * @Route("/journals/edit", name="journals_edit", methods={"GET"})
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

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
                    'login' => $this->getParameter('app.env') == 'dev' ? $this->generateUrl('app_login') : $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'journals' => json_encode($this->manager->getAllJson()),
            ]
        );
    }

    /**
     * @Route("/journals/{id}", name="journal_get", methods={"GET"})
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getSingle(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
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
     * @Route("/journals/{primaryId}/{secondaryId}", name="journal_merge", methods={"PUT"})
     * @param  int    $primaryId   first journal id (will stay)
     * @param  int    $secondaryId second journal id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/journals", name="journal_post", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/journals/{id}", name="journal_put", methods={"PUT"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/journals/{id}", name="journal_delete", methods={"DELETE"})
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
