<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

const M_INDEX = 'documents';
const M_TYPE = 'manuscript';
const MC_INDEX = 'contents';
const MC_TYPE = 'manuscript';

class ManuscriptController extends Controller
{
    /**
     * @Route("/manuscripts/search/")
     */
    public function searchManuscripts(Request $request)
    {
        // TODO: check if the user has rights to access all results
        return $this->render(
            'search.html.twig'
        );
    }

    /**
     * @Route("/manuscripts/search_api/")
     */
    public function searchManuscriptsAPI(Request $request)
    {
        // TODO: process POST parameters as well (->request->all())
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
                $es_params['orderBy'] = [
                    'city.name.keyword',
                    'library.name.keyword',
                    'fund.name.keyword',
                    'shelf.keyword',
                ];
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
        if (isset($params['filters'])) {
            $filters = json_decode($params['filters'], true);
            if (isset($filters) && is_array($filters)) {
                $es_params['filters'] = self::classifyFilters($filters);
            }
        }

        $search_result = $this->get('elasticsearch_service')->search(
            M_INDEX,
            M_TYPE,
            $es_params
        );

        return new Response(json_encode($search_result));
    }

    /**
     * @Route("/manuscripts/filtervalues")
     */
    public function getFilterValues(Request $request)
    {
        $filters = [];
        if (json_decode($request->getContent(), true) !== null) {
            $filters = self::classifyFilters(json_decode($request->getContent(), true));
        }
        $result = $this->get('elasticsearch_service')->aggregate(
            M_INDEX,
            M_TYPE,
            self::classifyFilters(['city', 'library', 'fund', 'content', 'patron', 'scribe', 'origin']),
            $filters
        );
        return new JsonResponse($result);
    }

    private static function classifyFilters(array $filters): array
    {
        // $filters can be a sequential (aggregation) or an associative (query) array
        $result = [];
        foreach ($filters as $key => $value) {
            if (isset($value) && $value != '') {
                switch (is_int($key) ? $value : $key) {
                    case 'city':
                    case 'library':
                    case 'fund':
                        if (is_int($key)) {
                            $result['object'][] = $value;
                        } else {
                            $result['object'][$key] = $value;
                        }
                        break;
                    case 'shelf':
                        $result['text'][$key] = $value;
                        break;
                    case 'date':
                        $result['date_range'][] = [
                            'floorField' => 'date_floor_year',
                            'ceilingField' => 'date_ceiling_year',
                            'startDate' => $value[0],
                            'endDate' => $value[1],
                        ];
                        break;
                    case 'content':
                    case 'patron':
                    case 'scribe':
                    case 'origin':
                        if (is_int($key)) {
                            $result['nested'][] = $value;
                        } else {
                            $result['nested'][$key] = $value;
                        }
                        break;
                }
            }
        }
        return $result;
    }
}
