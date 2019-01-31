<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
        $this->throwErrorIfNotJson($request);

        return new JsonResponse(
            $this->get(static::MANAGER)->getAllMiniShortJson()
        );
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
                    'journal_issue_deps_by_journal' => $this->generateUrl('journal_issue_deps_by_journal', ['id' => 'journal_id']),
                    'journal_post' => $this->generateUrl('journal_post'),
                    'journal_merge' => $this->generateUrl('journal_merge', ['primaryId' => 'primary_id', 'secondaryId' => 'secondary_id']),
                    'journal_put' => $this->generateUrl('journal_put', ['id' => 'journal_id']),
                    'journal_delete' => $this->generateUrl('journal_delete', ['id' => 'journal_id']),
                    'login' => $this->generateUrl('saml_login'),
                ]),
                'journals' => json_encode($this->get(self::MANAGER)->getAllMiniShortJson()),
            ]
        );
    }

    /**
     * @Route("/journals/{id}", name="journal_get")
     * @Method("GET")
     * @param int     $id
     * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');
            try {
                $object = $this->get(static::MANAGER)->getFull($id);
            } catch (NotFoundHttpException $e) {
                return new JsonResponse(
                    ['error' => ['code' => Response::HTTP_NOT_FOUND, 'message' => $e->getMessage()]],
                    Response::HTTP_NOT_FOUND
                );
            }
            return new JsonResponse($object->getJson());
        } else {
            // Let the 404 page handle the not found exception
            $object = $this->get(static::MANAGER)->getFull($id);
            return $this->render(
                static::TEMPLATE_FOLDER . 'detail.html.twig',
                [
                    $object::CACHENAME => $object,
                    'issuesArticles' => $this->get(static::MANAGER)->getIssuesArticles($id),
                ]
            );
        }
    }

    /**
     * @Route("/journals/{primaryId}/{secondaryId}", name="journal_merge")
     * @Method("PUT")
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
        return parent::delete($id, $request);
    }
}
