<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\ObjectStorage\MetreManager;

class MetreController extends BaseController
{
    public function __construct(MetreManager $metreManager)
    {
        $this->manager = $metreManager;
        $this->templateFolder = '@App/Metre/';
    }

    /**
     * @Route("/metres", name="metres_get")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/metres/edit", name="metres_edit")
     * @Method("GET")
     * @return Response
     */
    public function edit()
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            '@App/Metre/edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'metres_get' => $this->generateUrl('metres_get'),
                    'occurrence_deps_by_metre' => $this->generateUrl('occurrence_deps_by_metre', ['id' => 'metre_id']),
                    'occurrence_get' => $this->generateUrl('occurrence_get', ['id' => 'occurrence_id']),
                    'type_deps_by_metre' => $this->generateUrl('type_deps_by_metre', ['id' => 'metre_id']),
                    'type_get' => $this->generateUrl('type_get', ['id' => 'type_id']),
                    'metre_post' => $this->generateUrl('metre_post'),
                    'metre_put' => $this->generateUrl('metre_put', ['id' => 'metre_id']),
                    'metre_delete' => $this->generateUrl('metre_delete', ['id' => 'metre_id']),
                    'login' => $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'metres' => json_encode(
                    $this->manager->getAllJson()
                ),
            ]
        );
    }

    /**
     * @Route("/metres", name="metre_post")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request)
    {
        return parent::post($request);
    }

    /**
     * @Route("/metres/{id}", name="metre_put")
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
     * @Route("/metres/{id}", name="metre_delete")
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
