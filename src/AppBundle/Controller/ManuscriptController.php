<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

const TYPE = 'manuscript';

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

        // Sorting
        if (isset($params['orderBy'])) {
            if (($params['orderBy']) == 'name') {
                $es_params['orderBy'] = ['name.keyword'];
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
            TYPE,
            $es_params
        );

        return new Response(json_encode($search_result));
    }

    /**
     * @Route("/manuscripts/suggest_api/{field}/{text}")
     */
    public function suggestManuscriptsAPI(string $field, string $text)
    {
        $suggestion_result = $this->get('elasticsearch_service')->suggest(
            'manuscript',
            $field,
            $text
        );
        return new Response(json_encode($suggestion_result));
    }

    /**
     * @Route("/manuscripts/search/")
     */
    public function searchManuscripts(Request $request)
    {
        // TODO: check if the user has rights to access all results
        return $this->render(
            'search.html.twig',
            [
                'title' => 'Manuscripts'
            ]
        );
    }
}
