<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class OccurrenceController extends Controller
{
    /**
     * @Route("/occurrences/", name="occurrences_search")
     */
    public function searchOccurrences(Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * @Route("/occurrences/{id}", name="occurrence_show")
     */
    public function getOccurrence(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * Get all occurrences that have a dependency on a manuscript
     * (document_contains)
     * @Route("/occurrences/manuscripts/{id}", name="occurrence_deps_by_manuscript")
     * @Method("GET")
     * @param  int    $id manuscript id
     * @param Request $request
     */
    public function getOccurrenceDepsByManuscript(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            $occurrences = $this
                ->get('occurrence_manager')
                ->getOccurrencesDependenciesByManuscript($id);
            return new JsonResponse(ArrayToJson::arrayToShortJson($occurrences));
        } else {
            throw new NotFoundHttpException();
        }
    }
}
