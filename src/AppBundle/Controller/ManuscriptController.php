<?php

namespace AppBundle\Controller;

use AppBundle\Exceptions\NotFoundInDatabaseException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
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
        return $this->render(
            'AppBundle:Manuscript:overview.html.twig'
        );
    }

    /**
     * @Route("/manuscripts/search_api/")
     */
    public function searchManuscriptsAPI(Request $request)
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

        return new JsonResponse($search_result);
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
            self::classifyFilters(['city', 'library', 'collection', 'content', 'patron', 'scribe', 'origin']),
            $filters
        );
        // Make it possible to filter on all manuscripts without collection
        if (array_key_exists('collection', $result)) {
            $result['collection'][] = [
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
                    case 'collection':
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
     * @Route("/manuscripts/{id}", name="manuscript_show")
     */
    public function getManuscript(int $id, Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return $this->getManuscriptJSON($id);
        }
        return $this->getManuscriptHTML($id);
    }

    public function getManuscriptHTML(int $id)
    {
        $manuscript = $this->get('manuscript_manager')->getManuscriptById($id);

        if (empty($manuscript)) {
            throw $this->createNotFoundException('There is no manuscript with the requested id.');
        }

        return $this->render(
            'AppBundle:Manuscript:detail.html.twig',
            ['manuscript' => $manuscript]
        );
    }

    public function getManuscriptJSON(int $id)
    {
        $dms = $this->get('database_manuscript_service');

        // Check if manuscript with id exists
        try {
            $location = $dms->getLocation($id);
        } catch (NotFoundInDatabaseException $e) {
            throw $this->createNotFoundException('There is no manuscript with the requested id.');
        }

        return new JsonResponse([
            'location' => $location,
            'diktyon' => (int)$dms->getDiktyon($id),
        ]);
    }

    /**
     * @Route("/manuscripts/{id}/edit")
     */
    public function editManuscript(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        return $this->render(
            'AppBundle:Manuscript:edit.html.twig',
            ['id' => $id]
        );
    }
}
