<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\JournalIssueManager;
use App\ObjectStorage\JournalManager;
use App\Security\Roles;

class JournalIssueController extends BaseController
{
    public function __construct(JournalIssueManager $journalIssueManager)
    {
        $this->manager = $journalIssueManager;
        $this->templateFolder = 'JournalIssue/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journal_issues', name: 'journal_issues_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        if ($request->query->get('mini') === '1') {
            return parent::getAllMini($request);
        }
        return parent::getAll($request);
    }

    /**
     * Get all journal issues that have a dependency on a journal
     * (journal_issue)
     * @param  int    $id journal id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journal_issues/journals/{id}', name: 'journal_issue_deps_by_journal', methods: ['GET'])]
    public function getDepsByJournal(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getJournalDependencies');
    }

    /**
     * @param JournalManager $journalManager
     * @return Response
     */
    #[Route(path: '/journal_issues/edit', name: 'journal_issues_edit', methods: ['GET'])]
    public function edit(JournalManager $journalManager): Response
    {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'journal_issues_get' => $this->generateUrl('journal_issues_get'),
                    'article_deps_by_journal_issue' => $this->generateUrl('article_deps_by_journal_issue', ['id' => 'journal_issue_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'journal_issue_post' => $this->generateUrl('journal_issue_post'),
                    'journal_issue_put' => $this->generateUrl('journal_issue_put', ['id' => 'journal_issue_id']),
                    'journal_issue_delete' => $this->generateUrl('journal_issue_delete', ['id' => 'journal_issue_id']),
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'data'=> json_encode([
                    'journalIssues' => $this->manager->getAllJson(),
                    'journals' => $journalManager->getAllMiniShortJson(),
                ]),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journal_issues', name: 'journal_issue_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        return parent::post($request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journal_issues/{id}', name: 'journal_issue_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        return parent::put($id, $request);
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/journal_issues/{id}', name: 'journal_issue_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }
}
