<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class OriginController extends Controller
{
    /**
     * @Route("/origins", name="origins_get")
     * @param Request $request
     */
    public function getOrigins(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $origins = ArrayToJson::arrayToJson(
            $this->get('origin_manager')->getAllOrigins()
        );

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse($origins);
        }
        return $this->render(
            'AppBundle:Origin:overview.html.twig',
            [
                'origins' => json_encode($origins),
            ]
        );
    }
}
