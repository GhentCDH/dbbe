<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class JournalController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'journal_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Journal:';

    /**
     * @Route("/journals", name="journals_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/journals/edit", name="journals_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER  . 'edit.html.twig',
            [
                'urls' => json_encode([
                    'journals_get' => $this->generateUrl('journals_get'),
                    'article_deps_by_journal' => $this->generateUrl('article_deps_by_journal', ['id' => 'journal_id']),
                    'article_get' => $this->generateUrl('article_get', ['id' => 'article_id']),
                    'journal_post' => $this->generateUrl('journal_post'),
                    'journal_put' => $this->generateUrl('journal_put', ['id' => 'journal_id']),
                    'journal_delete' => $this->generateUrl('journal_delete', ['id' => 'journal_id']),
                    'login' => $this->generateUrl('login'),
                ]),
                'journals' => json_encode(
                    ArrayToJson::arrayToJson($this->get(self::MANAGER)->getAll())
                ),
            ]
        );
    }

    /**
     * @Route("/journals", name="journal_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/journals/{id}", name="journal_put")
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
     * @Route("/journals/{id}", name="journal_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id);
    }
}
