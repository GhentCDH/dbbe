<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\ArticleManager;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\PersonManager;
use App\ObjectStorage\RoleManager;
use App\Security\Roles;

class ArticleController extends BaseController
{
    public function __construct(ArticleManager $articleManager)
    {
        $this->manager = $articleManager;
        $this->templateFolder = 'Article/';
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles', name: 'articles_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMini($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
     * @param PersonManager $personManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    #[Route(path: '/articles/add', name: 'article_add', methods: ['GET'])]
    public function add(
        PersonManager $personManager,
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        $args = func_get_args();
        $args[] = null;

        return call_user_func_array([$this, 'edit'], $args);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    #[Route(path: '/articles/{id}', name: 'article_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all articles that have a dependency on a journal issue
     * (document_contains)
     * @param  int    $id journal issue id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles/journal_issues/{id}', name: 'article_deps_by_journal_issue', methods: ['GET'])]
    public function getDepsByJournalIssue(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getJournalIssueDependencies');
    }

    /**
     * Get all articles that have a dependency on a person
     * (bibrole)
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles/persons/{id}', name: 'article_deps_by_person', methods: ['GET'])]
    public function getDepsByPerson(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all articles that have a dependency on a role
     * (bibrole)
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles/roles/{id}', name: 'article_deps_by_role', methods: ['GET'])]
    public function getDepsByRole(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all articles that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles/managements/{id}', name: 'article_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles', name: 'article_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Article added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles/{id}', name: 'article_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Article data successfully saved.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/articles/{id}', name: 'article_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }

    /**
     * @param PersonManager $personManager
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    #[Route(path: '/articles/{id}/edit', name: 'article_edit', methods: ['GET'])]
    public function edit(
        PersonManager $personManager,
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted(Roles::ROLE_EDITOR_VIEW);

        return $this->render(
            $this->templateFolder . 'edit.html.twig',
            [
                'id' => $id,
                'urls' => json_encode([
                    'article_get' => $this->generateUrl('article_get', ['id' => $id == null ? 'article_id' : $id]),
                    'article_post' => $this->generateUrl('article_post'),
                    'article_put' => $this->generateUrl('article_put', ['id' => $id == null ? 'article_id' : $id]),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'journals_get' => $this->generateUrl('journals_get'),
                    'journals_edit' => $this->generateUrl('journals_edit'),
                    'journal_issues_get' => $this->generateUrl('journal_issues_get', ['mini' => 1]),
                    'journal_issues_edit' => $this->generateUrl('journal_issues_edit'),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                ]),
                'data' => json_encode([
                    'article' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'modernPersons' => $personManager->getAllModernShortJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode($identifierManager->getByTypeJson('article')),
                'roles' => json_encode($roleManager->getByTypeJson('article')),
            ]
        );
    }
}
