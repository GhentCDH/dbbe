<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class BibVariaController extends EditController
{
    /**
     * @var string
     */
    const MANAGER = 'bib_varia_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:BibVaria:';

    /**
     * @Route("/bib_varia", name="bib_varias_get")
     * @Method("GET")
     * @param Request $request
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
     * @param Request $request
     */
    public function add(Request $request)
    {
        return parent::add($request);
    }

    /**
     * @Route("/bib_varia/{id}", name="bib_varia_get")
     * @Method("GET")
      * @param int     $id
      * @param Request $request
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
     * @param  int    $id role id
     * @param Request $request
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
     * @param  int    $id management id
     * @param Request $request
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
     * @param  int|null $id
     * @param Request $request
     */
    public function edit(int $id = null, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            self::TEMPLATE_FOLDER . 'edit.html.twig',
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
                        : $this->get(self::MANAGER)->getFull($id)->getJson(),
                    'managements' => $this->get('management_manager')->getAllShortJson(),
                ]),
                'identifiers' => json_encode($this->get('identifier_manager')->getByTypeJson('bibVaria')),
                'roles' => json_encode($this->get('role_manager')->getByTypeJson('bibVaria')),
                // @codingStandardsIgnoreEnd
            ]
        );
    }
}
