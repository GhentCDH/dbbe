<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Utils\ArrayToJson;

class OccurrenceController extends Controller
{
    /**
     * @Route("/occurrences/", name="occurrences_search")
     */
    public function searchOccurrences(Request $request)
    {
        return $this->render(
            'AppBundle:Occurrence:overview.html.twig'
        );
    }

    /**
     * @Route("/occurrences/search_api/", name="occurrences_search_api")
     */
    public function searchOccurrencesAPI(Request $request)
    {
        $params = $request->query->all();

        $es_params = [];

        // Pagination
        if (isset($params['limit']) && is_numeric($params['limit'])) {
            $es_params['limit'] = $params['limit'];
        }
        if (isset($params['page']) && is_numeric($params['page'])) {
            $es_params['page'] = $params['page'];
        }


        // Sorting
        if (isset($params['orderBy'])) {
            if (isset($params['ascending']) && is_numeric($params['ascending'])) {
                $es_params['ascending'] = $params['ascending'];
            }
            if (($params['orderBy']) == 'name') {
                $es_params['orderBy'] = ['name.keyword'];
            } elseif (($params['orderBy']) == 'date') {
                // when sorting in descending order => sort by ceiling, else: sort by floor
                if (isset($params['ascending']) && $params['ascending'] == 0) {
                    $es_params['orderBy'] = ['date_ceiling_year', 'date_floor_year'];
                } else {
                    $es_params['orderBy'] = ['date_floor_year', 'date_ceiling_year'];
                }
            }
        }

        // Filtering
        $filters = [];
        if (isset($params['filters'])) {
            $filters = json_decode($params['filters'], true);
        }

        if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
            $filters['public'] = 1;
        }

        if (isset($filters) && is_array($filters)) {
            // sanitize text_type
            if (!(isset($filters['text_type']) && in_array($filters['text_type'], ['any', 'all', 'phrase']))) {
                $filters['text_type'] = 'any';
            }
            $es_params['filters'] = $filters;
        }

        $result = $this->get('occurrence_elastic_service')->searchAndAggregate(
            $es_params
        );

        return new JsonResponse($result);
    }

    /**
     * @Route("/occurrences/add", name="occurrence_add")
     * @Method("GET")
     * @param Request $request
     */
    public function addOccurrence(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->editOccurrence(null, $request);
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

    /**
     * @Route("/occurrences/{id}", name="occurrence_delete")
     * @Method("DELETE")
     * @param  int    $id occurrnence id
     * @param Request $request
     * @return Response
     */
    public function deleteOccurrence(int $id, Request $request)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * @Route("/occurrences/{id}/edit", name="occurrence_edit")
     * @param  int|null $id occurrence id
     * @param Request $request
     * @return Response
     */
    public function editOccurrence(int $id = null, Request $request)
    {
        throw new \Exception('Not implemented');
    }
}
