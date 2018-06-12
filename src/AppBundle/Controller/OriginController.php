<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OriginController extends Controller
{
    /**
     * @Route("/origins", name="origins_get")
     * @param Request $request
     */
    public function getOrigins(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse(
                ArrayToJson::arrayToJson(
                    $this->get('origin_manager')->getAllOrigins()
                )
            );
        }
        throw new BadRequestHttpException('Only JSON requests allowed.');
    }

    /**
     * @Route("/origins/edit", name="origins_edit")
     * @param Request $request
     */
    public function editOrigins(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->render(
            'AppBundle:Origin:edit.html.twig',
            [
                'urls' => json_encode([
                    'origins_get' => $this->generateUrl('origins_get'),
                    'manuscript_deps_by_institution' => $this->generateUrl('manuscript_deps_by_institution', ['id' => 'institution_id']),
                    'manuscript_get' => $this->generateUrl('manuscript_get', ['id' => 'manuscript_id']),
                    'regions_get' => $this->generateUrl('regions_get'),
                    'region_put' => $this->generateUrl('region_put', ['id' => 'region_id']),
                    'monastery_post' => $this->generateUrl('monastery_post'),
                    'monastery_put' => $this->generateUrl('monastery_put', ['id' => 'monastery_id']),
                    'monastery_delete' => $this->generateUrl('monastery_delete', ['id' => 'monastery_id']),
                ]),
                'origins' => json_encode(
                    ArrayToJson::arrayToJson(
                        $this->get('origin_manager')->getAllOrigins()
                    )
                ),
            ]
        );
    }
}
