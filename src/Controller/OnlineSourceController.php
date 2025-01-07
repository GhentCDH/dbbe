<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\OnlineSourceManager;
use App\ObjectStorage\RoleManager;
use App\Security\Roles;

class OnlineSourceController extends BaseController
{
    public function __construct(OnlineSourceManager $onlineSourceManager)
    {
        $this->manager = $onlineSourceManager;
        $this->templateFolder = 'OnlineSource/';
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    #[Route(path: '/onlinesources', name: 'online_sources_get', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse|RedirectResponse
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMini($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    #[Route(path: '/onlinesources/add', name: 'online_source_add', methods: ['GET'])]
    public function add(
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
    #[Route(path: '/onlinesources/{id}', name: 'online_source_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all online sources that have a dependency on an instutution
     * @param int $id institution id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/onlinesources/institutions/{id}', name: 'online_source_deps_by_institution', methods: ['GET'])]
    public function getDepsByInstitution(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getInstitutionDependencies');
    }

    /**
     * Get all online sources that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/onlinesources/managements/{id}', name: 'online_source_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/onlinesources', name: 'online_source_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Online source added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/onlinesources/{id}', name: 'online_source_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Online source data successfully saved.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/onlinesources/{id}', name: 'online_source_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        return parent::delete($id, $request);
    }

    /**
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    #[Route(path: '/onlinesources/{id}/edit', name: 'online_source_edit', methods: ['GET'])]
    public function edit(
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
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'online_source_get' => $this->generateUrl('online_source_get', ['id' => $id == null ? 'online_source_id' : $id]),
                    'online_source_post' => $this->generateUrl('online_source_post'),
                    'online_source_put' => $this->generateUrl('online_source_put', ['id' => $id == null ? 'online_source_id' : $id]),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    // @codingStandardsIgnoreEnd
                    'login' => $this->generateUrl('idci_keycloak_security_auth_connect'),
                ]),
                'data' => json_encode([
                    'onlineSource' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode(
                    $identifierManager->getByTypeJson('onlineSource')
                ),
                'roles' => json_encode(
                    $roleManager->getByTypeJson('onlineSource')
                ),
            ]
        );
    }
}
