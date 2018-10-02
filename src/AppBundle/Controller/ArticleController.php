<?php

namespace AppBundle\Controller;

use AppBundle\Utils\ArrayToJson;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ArticleController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'article_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Article:';

    /**
     * @Route("/articles/add", name="article_add")
     * @Method("GET")
     * @param Request $request
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/articles/{id}", name="article_get")
     * @Method("GET")
      * @param int     $id
      * @param Request $request
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all articles that have a dependency on a journal
     * (document_contains)
     * @Route("/articles/journals/{id}", name="article_deps_by_journal")
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
     * Get all articles that have a dependency on a person
     * (bibrole)
     * @Route("/articles/persons/{id}", name="article_deps_by_person")
     * @Method("GET")
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByPerson(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all articles that have a dependency on a role
     * (bibrole)
     * @Route("/articles/roles/{id}", name="article_deps_by_role")
     * @Method("GET")
     * @param  int    $id role id
     * @param Request $request
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * @Route("/articles", name="article_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Article added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/articles/{id}", name="article_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Article data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/articles/{id}", name="article_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/articles/{id}/edit", name="article_edit")
     * @Method("GET")
     * @param  int|null $id
     * @param Request $request
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    'article_get' => $this->generateUrl('article_get', ['id' => $id == null ? 'article_id' : $id]),
                    'article_post' => $this->generateUrl('article_post'),
                    'article_put' => $this->generateUrl('article_put', ['id' => $id == null ? 'article_id' : $id]),
                    'journals_edit' => $this->generateUrl('journals_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'article' => empty($id)
                        ? null
                        : $this->get(self::MANAGER)->getFull($id)->getJson(),
                    'modernPersons' => ArrayToJson::arrayToShortJson(
                        $this->get('person_manager')->getAllModernPersons()
                    ),
                    'journals' => ArrayToJson::arrayToShortJson(
                        $this->get('journal_manager')->getAll()
                    ),
                ]),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('identifier_manager')->getIdentifiersByType('article')
                    )
                ),
                'roles' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('role_manager')->getRolesByType('article')
                    )
                ),
            ]
        );
    }
}
