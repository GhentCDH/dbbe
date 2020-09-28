<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\ObjectStorage\BibVariaManager;
use AppBundle\ObjectStorage\IdentifierManager;
use AppBundle\ObjectStorage\ManagementManager;
use AppBundle\ObjectStorage\RoleManager;

class BibVariaController extends BaseController
{
    public function __construct(BibVariaManager $bibVariaManager)
    {
        $this->manager = $bibVariaManager;
        $this->templateFolder = '@App/BibVaria/';
    }

    /**
     * @Route("/bib_varia", name="bib_varias_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function getAll(Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return parent::getAllMini($request);
        }
        // Redirect to search page if not a json request
        return $this->redirectToRoute('bibliographies_search', ['request' =>  $request], 301);
    }

    /**
     * @Route("/bib_varia/add", name="bib_varia_add")
     * @Method("GET")
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @return mixed
     */
    public function add(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        $args = func_get_args();
        $args[] = null;

        return call_user_func_array([$this, 'edit'], $args);
    }

    /**
     * @Route("/bib_varia/{id}", name="bib_varia_get")
     * @Method("GET")
     * @param int $id
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function getSingle(int $id, Request $request)
    {
        return parent::getSingle($id, $request);
    }

    /**
     * Get all bib varias that have a dependency on a person
     * (bibrole)
     * @Route("/bib_varia/persons/{id}", name="bib_varia_deps_by_person")
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
     * Get all bib varias that have a dependency on a role
     * (bibrole)
     * @Route("/bib_varia/roles/{id}", name="bib_varia_deps_by_role")
     * @Method("GET")
     * @param int $id role id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByRole(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getRoleDependencies');
    }

    /**
     * Get all bib varias that have a dependency on a management collection
     * (reference)
     * @Route("/bib_varia/managements/{id}", name="bib_varia_deps_by_management")
     * @Method("GET")
     * @param int $id management id
     * @param Request $request
     * @return JsonResponse
     */
    public function getDepsByManagement(int $id, Request $request)
    {
        return $this->getDependencies($id, $request, 'getManagementDependencies');
    }

    /**
     * @Route("/bib_varia", name="bib_varia_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Bib varia added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/bib_varia/{id}", name="bib_varia_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'Bib varia data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/bib_varia/{id}", name="bib_varia_delete")
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
     * @Route("/bib_varia/{id}/edit", name="bib_varia_edit")
     * @Method("GET")
     * @param ManagementManager $managementManager
     * @param IdentifierManager $identifierManager
     * @param RoleManager $roleManager
     * @param int|null $id
     * @return Response
     */
    public function edit(
        ManagementManager $managementManager,
        IdentifierManager $identifierManager,
        RoleManager $roleManager,
        int $id = null
    ) {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

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
                    'login' => $this->generateUrl('saml_login'),
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
