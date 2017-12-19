<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

const M_INDEX = 'documents';
const M_TYPE = 'manuscript';
const MC_INDEX = 'contents';
const MC_TYPE = 'manuscript';

class ManuscriptController extends Controller
{
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
                $es_params['orderBy'] = ['city.keyword', 'library.keyword', 'fund.keyword', 'shelf.keyword'];
            } elseif (($params['orderBy']) == 'date') {
                // when sorting in descending order => sort by ceiling, else: sort by floor
                if ($isset($params['ascending']) && $params['ascending'] == 0) {
                    $es_params['orderBy'] = ['date_ceiling'];
                } else {
                    $es_params['orderBy'] = ['date_floor'];
                }
            } elseif (($params['orderBy']) == 'genre') {
                $es_params['orderBy'] = ['parent_genre.keyword', 'child_genre.keyword'];
            }
        }

        // Filtering
        if (isset($params['filters'])) {
            $filters = json_decode($params['filters']);
            if (isset($filters) && is_object($filters)) {
                foreach ($filters as $key => $value) {
                    if (isset($value) && $value != '') {
                        $es_params['filters'][$key] = $value;
                    }
                }
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
     * @Route("/manuscripts/suggest_api/name/{text}")
     */
    public function suggestManuscriptsAPI(string $text)
    {
        $suggestionResult = $this->get('elasticsearch_service')->suggest(
            M_INDEX,
            M_TYPE,
            'name',
            $text
        );

        return $this->suggestAPIFormatter($suggestionResult, 'name');
    }

    /**
     * @Route("/manuscripts/suggest_api/content/{text}")
     */
    public function suggestManuscriptContentsAPI(string $text)
    {
        $suggestionResult = $this->get('elasticsearch_service')->suggest(
            MC_INDEX,
            MC_TYPE,
            'name',
            str_replace(':', ' ', $text)
        );

        return $this->suggestAPIFormatter($suggestionResult, 'name');
    }

    private function suggestAPIFormatter(array $rawResults, $field)
    {
        $results = [];
        foreach ($rawResults as $rawResult) {
            $results[] = $rawResult['_source'][$field];
        }
        return new Response(json_encode($results));
    }

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
}
