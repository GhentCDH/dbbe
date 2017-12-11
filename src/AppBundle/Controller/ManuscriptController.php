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

        // Sorting
        if (isset($params['orderBy'])) {
            if (($params['orderBy']) == 'name') {
                $params['orderBy'] = ['name.keyword'];
            } elseif (($params['orderBy']) == 'date') {
                // when sorting in descending order => sort by ceiling, else: sort by floor
                if ($isset($params['ascending']) && $params['ascending'] == 0) {
                    $params['orderBy'] = ['date_ceiling'];
                } else {
                    $params['orderBy'] = ['date_floor'];
                }
            } elseif (($params['orderBy']) == 'genre') {
                $params['orderBy'] = ['parent_genre.keyword', 'child_genre.keyword'];
            } else {
                unset($params['orderBy']);
            }
        }

        // Filtering
        if (isset($params['filters'])) {
            $params['filters'] = json_decode($params['filters']);
        }

        $search_result = $this->get('elasticsearch_service')->search(
            TYPE,
            $params
        );
        // foreach ($search_result['data'] as $id => $result) {
        //     echo($result['child_genre']);
        //     if (isset($result['parent_genre'])) {
        //         $search_result['data'][$id]['genre'] = $result['parent_genre'] + ': ' + $result['child_genre'];
        //     } else {
        //         $search_result['data'][$id]['genre'] = $result['child_genre'];
        //     }
        // }
        return new Response(json_encode($search_result));
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
