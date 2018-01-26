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

        $cache = $this->get('cache.app');
        $cacheItem = $cache->getItem('manuscript_search.' . self::manuscriptSearchCacheKey($params));
        if ($cacheItem->isHit()) {
            return new JsonResponse($cacheItem->get(), 200, [], true);
        }

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

        $json = json_encode($search_result);

        $cacheItem->set($json);
        $cacheItem->tag(['manuscripts']);
        $cache->save($cacheItem);

        return new JsonResponse($json, 200, [], true);
    }

    private static function manuscriptSearchCacheKey(array $params): string
    {
        $cacheKey = ''
            . $params['ascending'] . '_'
            . $params['byColumn'] . '_'
            . $params['limit'] . '_'
            . $params['orderBy'] . '_'
            . $params['page'];
        if (isset($params['filters'])) {
            foreach (json_decode($params['filters']) as $key => $value) {
                $cacheKey .= '_' . $key;
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $cacheKey .= '_' . $subKey . '_' . $subValue;
                    }
                } else {
                    $cacheKey .= '_' . $value;
                }
            }
        }
        return $cacheKey;
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

        $cache = $this->get('cache.app');
        $cacheItem = $cache->getItem('manuscript_filterValues.' . self::manuscriptFilterValuesCacheKey($filters));
        if ($cacheItem->isHit()) {
            return new JsonResponse($cacheItem->get(), 200, [], true);
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

        $json = json_encode($result);

        $cacheItem->set($json);
        $cacheItem->tag(['manuscripts']);
        $cache->save($cacheItem);

        return new JsonResponse($json, 200, [], true);
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

    private static function manuscriptFilterValuesCacheKey(array $filters): string
    {
        $cacheKey = '';
        foreach ($filters as $key => $value) {
            $cacheKey .= '_' . $key;
            foreach ($value as $subKey => $subValue) {
                $cacheKey .= '_' . $subKey;
                if (is_array($subValue)) {
                    foreach ($subValue as $subSubKey => $subSubValue) {
                        $cacheKey .= '_' . $subSubKey . '_' . $subSubValue;
                    }
                } else {
                    $cacheKey .= '_' . $subValue;
                }
            }
        }
        return $cacheKey;
    }

    /**
     * @Route("/manuscripts/{id}", name="getManuscript")
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
        $dms = $this->get('database_manuscript_service');
        $params = [];

        // Get name, create not found page if it is not found in the database
        try {
            $params['name'] = $dms->getName($id);
        } catch (NotFoundInDatabaseException $e) {
            throw $this->createNotFoundException('There is no manuscript with the requested id.');
        }

        $persons = $dms->getPersons($id);
        $comments = $dms->getComments($id);

        // Other information
        $params['infos'] = [
            'content' => [
                'title' => 'Content',
                'content' => $dms->getContents($id),
            ],
            'date' => [
                'title' => 'Date',
                'content' => [ $dms->getCompletionDate($id) ],
            ],
            'patrons' => [
                'title' => 'Patron(s)',
                'content' => $persons['patrons'],
                'type' => 'link',
                'base_url' => '/persons/',
            ],
            'scribes' => [
                'title' => 'Scribe(s)',
                'content' => $persons['scribes'],
                'type' => 'link',
                'base_url' => '/persons/',
            ],
            'persons' => [
                'title' => 'Related person(s)',
                'content' => $persons['relatedPersons'],
                'type' => 'link',
                'base_url' => '/persons/',
            ],
            'origin' => [
                'title' => 'Origin',
                'content' => [ $dms->getOrigin($id) ],
            ],
            'bibliography' => [
                'title' => 'Bibliography',
                'content' => $dms->getBibliographys($id),
                'type' => 'link_expl',
                'base_url' => '/bibliographies/',
            ],
            'pinakes' => [
                'title' => 'Link to Pinakes',
                'content' => [ $dms->getDiktyon($id) ],
                'type' => 'link_without_name',
                'base_url' => 'http://pinakes.irht.cnrs.fr/notices/cote/id/',
            ],
            'public_comment' => [
                'title' => 'Comment',
                'content' => !empty($comments) ? [$comments['public_comment']] : [],
            ],
            'occurrences' => [
                'title' => 'Occurrences',
                'content' => $dms->getOccurrences($id),
                'type' => 'link',
                'base_url' => '/occurrences/',
            ]
        ];

        // Internal fields
        if ($this->get('security.authorization_checker')->isGranted('ROLE_VIEW_INTERNAL')) {
            $params['infos']['internal_comment'] = [
                'title' => 'Internal comment',
                'content' => !empty($comments) ? [$comments['private_comment']] : [],
                'internal' => true,
            ];
            $params['infos']['illustrated'] = [
                'title' => 'Illustrated',
                'content' => [ $dms->getIsIllustrated($id) ? 'Yes': 'No'],
                'internal' => true,
            ];
        }

        // Do not display empty fields
        foreach ($params['infos'] as $key => $value) {
            // empty array or first element is null
            if (count($value['content']) == 0 || reset($value['content']) == null) {
                unset($params['infos'][$key]);
            }
        }

        return $this->render(
            'AppBundle:Manuscript:detail.html.twig',
            $params
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
