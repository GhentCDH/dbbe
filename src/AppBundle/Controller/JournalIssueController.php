<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JournalIssueController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'journal_issue_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:JournalIssue:';

    /**
     * @Route("/journal_issues", name="journal_issues_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
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
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'urls' => json_encode([
                    'journal_issues_get' => $this->generateUrl('journal_issues_get'),
                    'article_deps_by_journal_issue' => $this->generateUrl('article_deps_by_journal_issue', ['id' => 'journal_issue_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'journal_issue_post' => $this->generateUrl('journal_issue_post'),
                    'journal_issue_put' => $this->generateUrl('journal_issue_put', ['id' => 'journal_issue_id']),
                    'journal_issue_delete' => $this->generateUrl('journal_issue_delete', ['id' => 'journal_issue_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'data'=> json_encode([
                    'journalIssues' => $this->get(self::MANAGER)->getAllJson(),
                    'journals' => $this->get('journal_manager')->getAllShortJson(),
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
