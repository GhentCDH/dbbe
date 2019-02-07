<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MetreController extends BaseController
{
    /**
     * @var string
     */
    const MANAGER = 'metre_manager';
    /**
     * @var string
     */
    const TEMPLATE_FOLDER = 'AppBundle:Metre:';

    /**
     * @Route("/metres", name="metres_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getAll(Request $request)
    {
        return parent::getAll($request);
    }

    /**
     * @Route("/metres/edit", name="metres_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Metre:edit.html.twig',
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
                    'login' => $this->generateUrl('login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'metres' => json_encode(
                    $this->get('metre_manager')->getAllJson()
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