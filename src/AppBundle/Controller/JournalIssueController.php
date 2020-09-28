<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\ObjectStorage\JournalIssueManager;
use AppBundle\ObjectStorage\JournalManager;

class JournalIssueController extends BaseController
{
    public function __construct(JournalIssueManager $journalIssueManager)
    {
        $this->manager = $journalIssueManager;
        $this->templateFolder = '@App/JournalIssue/';
    }

    /**
     * @Route("/journal_issues", name="journal_issues_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        if ($request->query->get('mini') === '1') {
            return parent::getAllMini($request);
        }
        return parent::getAll($request);
    }

    /**
     * Get all journal issues that have a dependency on a journal
     * (journal_issue)
     * @Route("/journal_issues/journals/{id}", name="journal_issue_deps_by_journal")
     * @Method("GET")
     * @param  int    $id journal id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByJournal(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getJournalDependencies');
    }

    /**
     * @Route("/journal_issues/edit", name="journal_issues_edit")
     * @Method("GET")
     * @param JournalManager $journalManager
     * @return Response
     */
    public function edit(JournalManager $journalManager)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

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
                    'login' => $this->generateUrl('saml_login'),
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
     * @Route("/journal_issues", name="journal_issue_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/journal_issues/{id}", name="journal_issue_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        return parent::put($id, $request);
    }

    /**
     * @Route("/journal_issues/{id}", name="journal_issue_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }
}
