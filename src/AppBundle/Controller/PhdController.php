<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\ObjectStorage\IdentifierManager;
use AppBundle\ObjectStorage\ManagementManager;
use AppBundle\ObjectStorage\PhdManager;
use AppBundle\ObjectStorage\RoleManager;

class PhdController extends BaseController
{
    public function __construct(PhdManager $phdManager)
    {
        $this->manager = $phdManager;
        $this->templateFolder = '@App/Phd/';
    }

    /**
     * @Route("/phd_theses", name="phds_get")
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
     * @Route("/phd_theses/add", name="phd_add")
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
     * @Route("/phd_theses/{id}", name="phd_get")
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
     * Get all PhD theses that have a dependency on a person
     * (bibrole)
     * @Route("/phd_theses/persons/{id}", name="phd_deps_by_person")
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
     * Get all PhD theses that have a dependency on a role
     * (bibrole)
     * @Route("/phd_theses/roles/{id}", name="phd_deps_by_role")
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
     * Get all PhD theses that have a dependency on a management collection
     * (reference)
     * @Route("/phd_theses/managements/{id}", name="phd_deps_by_management")
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
     * @Route("/phd_theses", name="phd_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        $response = parent::post($request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'PhD thesis added successfully.');
        }

        return $response;
    }

    /**
     * @Route("/phd_theses/{id}", name="phd_put")
     * @Method("PUT")
     * @param  int    $id
     * @param Request $request
     * @return JsonResponse
     */
    public function put(int $id, Request $request)
    {
        $response = parent::put($id, $request);

        if (!property_exists(json_decode($response->getcontent()), 'error')) {
            $this->addFlash('success', 'PhD thesis data successfully saved.');
        }

        return $response;
    }

    /**
     * @Route("/phd_theses/{primaryId}/{secondaryId}", name="phd_merge")
     * @Method("PUT")
     * @param  int    $primaryId   first PhD theses id (will stay)
     * @param  int    $secondaryId second PhD theses id (will be deleted)
     * @param Request $request
     * @return JsonResponse
     */
    public function merge(int $primaryId, int $secondaryId, Request $request)
    {
        return parent::merge($primaryId, $secondaryId, $request);
    }

    /**
     * @Route("/phd_theses/{id}", name="phd_delete")
     * @Method("DELETE")
     * @param  int    $id
     * @param Request $request
     * @return Response
     */
    public function delete(int $id, Request $request)
    {
        return parent::delete($id, $request);
    }

    /**
     * @Route("/phd_theses/{id}/edit", name="phd_edit")
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
                'id' => $id,
                'urls' => json_encode([
                    'phd_get' => $this->generateUrl('phd_get', ['id' => $id == null ? 'phd_id' : $id]),
                    'phd_post' => $this->generateUrl('phd_post'),
                    'phd_put' => $this->generateUrl('phd_put', ['id' => $id == null ? 'phd_id' : $id]),
                    'modern_persons_get' => $this->generateUrl('persons_get', ['type' => 'modern']),
                    'persons_search' => $this->generateUrl('persons_search'),
                    'managements_get' => $this->generateUrl('managements_get'),
                    'managements_edit' => $this->generateUrl('managements_edit'),
                    'login' => $this->generateUrl('saml_login'),
                ]),
                'data' => json_encode([
                    'phd' => empty($id)
                        ? null
                        : $this->manager->getFull($id)->getJson(),
                    'managements' => $managementManager->getAllShortJson(),
                ]),
                'identifiers' => json_encode($identifierManager->getByTypeJson('phd')),
                'roles' => json_encode($roleManager->getByTypeJson('phd')),
            ]
        );
    }
}
