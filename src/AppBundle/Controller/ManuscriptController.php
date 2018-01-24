<?php

namespace AppBundle\Controller;

use AppBundle\Exceptions\NotFoundInDatabaseException;
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
     * @Route("/manuscripts/")
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
        // Make it possible to filter on all manuscripts without fund
        if (array_key_exists('fund', $result)) {
            $result['fund'][] = [
                'id' => -1,
                'name' => 'No collection',
            ];
        }
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

    /**
     * @Route("/manuscripts/{id}")
     */
    public function getManuscript(int $id)
    {
        $dms = $this->get('database_manuscript_service');

        $params = [];

        // Get name, create not found page if it is not found in the database
        try {
            $params['name'] = $dms->getName($id);
        } catch (NotFoundInDatabaseException $e) {
            throw $this->createNotFoundException('There is no manuscript with the requested id.');
        }

        // Other information
        $params['infos'] = [
            'content' => [
                'title' => 'Content',
                'content' => $dms->getContents($id),
                'type' => 'multiple'
            ],
            'date' => [
                'title' => 'Date',
                'content' => $dms->getCompletionDate($id),
            ],
            'patrons' => [
                'title' => 'Patron(s)',
                'content' => $dms->getBibroles('patron', $id),
                'type' => 'multiple_link',
                'base_url' => '/persons/',
            ],
            'scribes' => [
                'title' => 'Scribe(s)',
                'content' => $dms->getBibroles('scribe', $id),
                'type' => 'multiple_link',
                'base_url' => '/persons/',
            ],
            'persons' => [
                'title' => 'Related person(s)',
                'content' => $dms->getRelatedPersons($id),
                'type' => 'multiple_link',
                'base_url' => '/persons/',
            ],
            'origin' => [
                'title' => 'Origin',
                'content' => $dms->getOrigin($id),
            ],
            'bibliography' => [
                'title' => 'Bibliography',
                'content' => $dms->getBibliographys($id),
                'type' => 'multiple_expl_link',
                'base_url' => '/bibliographies/',
            ],
            'pinakes' => [
                'title' => 'Link to Pinakes',
                'content' => $dms->getDiktyon($id),
                'type' => 'link_without_name',
                'base_url' => 'http://pinakes.irht.cnrs.fr/notices/cote/id/',
            ],
            'public_comment' => [
                'title' => 'Comment',
                'content' => $dms->getPublicComment($id),
            ],
            'occurrences' => [
                'title' => 'Occurrences',
                'content' => $dms->getOccurrences($id),
                'type' => 'multiple_link',
                'base_url' => '/occurrences/',
            ]
        ];

        // Do not display empty fields
        foreach ($params['infos'] as $key => $value) {
            if (empty($value['content'])) {
                unset($params['infos'][$key]);
            }
        }

        return $this->render(
            'manuscript.html.twig',
            $params
        );
    }
}
