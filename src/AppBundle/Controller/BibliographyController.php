<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Utils\ArrayToJson;

class BibliographyController extends Controller
{
    /**
    * @Route("/bibliographies/search", name="bibliographies_search")
    * @Method("GET")
    * @param Request $request
    */
    public function searchBibliographies(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');

        return $this->render(
            'AppBundle:Bibliography:overview.html.twig',
            [
                'urls' => json_encode([
                    'bibliographies_search_api' => $this->generateUrl('bibliographies_search_api'),
                    'book_get' => $this->generateUrl('book_get', ['id' => 'book_id']),
                    'book_edit' => $this->generateUrl('book_edit', ['id' => 'book_id']),
                    'book_delete' => $this->generateUrl('book_delete', ['id' => 'book_id']),
                ]),
                'data' => json_encode(
                    $this->get('bibliography_elastic_service')->searchAndAggregate(
                        $this->sanitize($request->query->all()),
                        $this->isGranted('ROLE_VIEW_INTERNAL')
                    )
                ),
                'identifiers' => json_encode(
                    ArrayToJson::arrayToJson($this->get('identifier_manager')->getPrimaryIdentifiersByType('book'))
                ),
            ]
        );
    }

    /**
     * @Route("/bibliographies/search_api", name="bibliographies_search_api")
     * @Method("GET")
     * @param Request $request
     */
    public function searchBibliographiesAPI(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_VIEW_INTERNAL');
        
        $result = $this->get('bibliography_elastic_service')->searchAndAggregate(
            $this->sanitize($request->query->all()),
            $this->isGranted('ROLE_VIEW_INTERNAL')
        );

        return new JsonResponse($result);
    }

    private function sanitize(array $params): array
    {
        $defaults = [
            'limit' => 25,
            'page' => 1,
            'ascending' => 1,
            'orderBy' => ['title.keyword'],
        ];
        $esParams = [];

        // Pagination
        if (isset($params['limit']) && is_numeric($params['limit'])) {
            $esParams['limit'] = $params['limit'];
        } else {
            $esParams['limit'] = $defaults['limit'];
        }
        if (isset($params['page']) && is_numeric($params['page'])) {
            $esParams['page'] = $params['page'];
        } else {
            $esParams['page'] = $defaults['page'];
        }


        // Sorting
        if (isset($params['orderBy'])) {
            if (isset($params['ascending']) && is_numeric($params['ascending'])) {
                $esParams['ascending'] = $params['ascending'];
            } else {
                $esParams['ascending'] = $defaults['ascending'];
            }
            if (($params['orderBy']) == 'title') {
                $esParams['orderBy'] = ['title.keyword'];
            } else {
                $esParams['orderBy'] = $defaults['orderBy'];
            }
        // Don't set default order if there is a text field filter
        } else if (!(isset($params['filters']['title']) || isset($params['filters']['comment']))) {
            $esParams['orderBy'] = $defaults['orderBy'];
        }

        // Filtering
        $filters = [];
        if (isset($params['filters']) && is_array($params['filters'])) {
            // TODO: detailed sanitation?
            $filters = $params['filters'];
        }

        // limit results to public if no access rights
        if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
            $filters['public'] = '1';
        }

        // set which comments should be searched
        if (isset($filters['comment'])) {
            if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
                $filters['public_comment'] = $filters['comment'];
                unset($filters['comment']);
            }
        }

        if (isset($filters) && is_array($filters)) {
            $esParams['filters'] = $filters;
        }

        return $esParams;
    }
}
