<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\ObjectStorage\BibVariaManager;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\ManagementManager;
use App\ObjectStorage\RoleManager;
use App\Security\Roles;

class BibVariaController extends BaseController
{
    public function __construct(BibVariaManager $bibVariaManager)
    {
        $this->manager = $bibVariaManager;
        $this->templateFolder = 'BibVaria/';
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    #[Route(path: '/bib_varia', name: 'bib_varias_get', methods: ['GET'])]
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
    #[Route(path: '/bib_varia/add', name: 'bib_varia_add', methods: ['GET'])]
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
    #[Route(path: '/bib_varia/{id}', name: 'bib_varia_get', methods: ['GET'])]
    public function getSingle(int $id, Request $request): JsonResponse|Response
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all bib varias that have a dependency on a person
     * (bibrole)
     * @param  int    $id person id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/bib_varia/persons/{id}', name: 'bib_varia_deps_by_person', methods: ['GET'])]
    public function getDepsByPerson(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getPersonDependencies');
    }

    /**
     * Get all bib varias that have a dependency on a role
     * (bibrole)
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/bib_varia/roles/{id}', name: 'bib_varia_deps_by_role', methods: ['GET'])]
    public function getDepsByRole(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all bib varias that have a dependency on a management collection
     * (reference)
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/bib_varia/managements/{id}', name: 'bib_varia_deps_by_management', methods: ['GET'])]
    public function getDepsByManagement(int $id, Request $request): JsonResponse
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/bib_varia', name: 'bib_varia_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Bib varia added successfully.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/bib_varia/{id}', name: 'bib_varia_put', methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Bib varia data successfully saved.');
        }

        return $response;
    }

    /**
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/bib_varia/{id}', name: 'bib_varia_delete', methods: ['DELETE'])]
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
    #[Route(path: '/bib_varia/{id}/edit', name: 'bib_varia_edit', methods: ['GET'])]
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
                // @codingStandardsIgnoreStart Generic.Files.LineLength
                'id' => $id,
                'urls' => json_encode([
                    'bib_varia_get' => $this->generateUrl('bib_varia_get', ['id' => $id == null ? 'bib_varia_id' : $id]),
                    'bib_varia_post' => $this->generateUrl('bib_varia_post'),
                    'bib_varia_put' => $this->generateUrl('bib_varia_put', ['id' => $id == null ? 'bib_varia_id' : $id]),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'bibliographies_search' => $this->generateUrl('bibliographies_search'),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('login'),
                ]),
                'data' => json_encode([
                    'bibVaria' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode($identifierManager->getByTypeJson('bibVaria')),
                'roles' => json_encode($roleManager->getByTypeJson('bibVaria')),
                // @codingStandardsIgnoreEnd
            ]
        );
    }
}
