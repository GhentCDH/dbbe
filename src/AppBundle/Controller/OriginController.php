<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OriginController extends Controller
{
    /**
     * @Route("/origins", name="origins_get")
     * @Method("GET")
     * @param Request $request
     */
    public function getOrigins(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            if ($request->query->get('type') == 'person') {
                // person edit page
                return new JsonResponse(
                    $this->get('origin_manager')->getByTypeShortJson('person')
                );
            } elseif ($request->query->get('type') == 'manuscript') {
                // manuscript edit page
                return new JsonResponse(
                    $this->get('origin_manager')->getByTypeShortJson('manuscript')
                );
            } else {
                // origins edit page
                return new JsonResponse(
                    // origins for manuscript is a superset of origins for persons
                    $this->get('origin_manager')->getByTypeJson('manuscript')
                );
            }
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/origins/edit", name="origins_edit")
     * @Method("GET")
     * @param Request $request
     */
    public function editOrigins(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Origin:edit.html.twig',
            [
                'urls' => json_encode([
                    // @codingStandardsIgnoreStart Generic.Files.LineLength
                    'origins_get' => $this->generateUrl('origins_get'),
                    'manuscript_deps_by_institution' => $this->generateUrl('manuscript_deps_by_institution', ['id' => 'institution_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'regions_edit' => $this->generateUrl('regions_edit'),
                    'region_put' => $this->generateUrl('region_put', ['id' => 'region_id']),
                    'monastery_post' => $this->generateUrl('monastery_post'),
                    'monastery_put' => $this->generateUrl('monastery_put', ['id' => 'monastery_id']),
                    'monastery_delete' => $this->generateUrl('monastery_delete', ['id' => 'monastery_id']),
                    'login' => $this->generateUrl('saml_login'),
                    // @codingStandardsIgnoreEnd
                ]),
                'origins' => json_encode(
                    // origins for manuscript is a superset of origins for persons
                    $this->get('origin_manager')->getByTypeJson('manuscript')
                ),
            ]
        );
    }
}
