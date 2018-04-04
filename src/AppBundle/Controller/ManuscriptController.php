<?php

namespace AppBundle\Controller;

use Ev;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Helpers\ArrayToJsonTrait;

class ManuscriptController extends Controller
{
    use ArrayToJsonTrait;

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
     * @Route("/manuscripts/search_api/", name="manuscripts_search_api")
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
        $filters = [];
        if (isset($params['filters'])) {
            $filters = json_decode($params['filters'], true);
        }

        if (!$this->isGranted('ROLE_VIEW_INTERNAL')) {
            $filters['public'] = true;
        }

        if (isset($filters) && is_array($filters)) {
            $es_params['filters'] = self::classifyFilters($filters);
        }

        $result = $this->get('elasticsearch_service')->search(
            'documents',
            'manuscript',
            $es_params
        );

        $aggregation_result = $this->get('elasticsearch_service')->aggregate(
            'documents',
            'manuscript',
            self::classifyFilters(['city', 'library', 'collection', 'content', 'patron', 'scribe', 'origin', 'public']),
            !empty($es_params['filters']) ? $es_params['filters'] : []
        );

        $result['aggregation'] = $aggregation_result;

        return new JsonResponse($result);
    }

    private static function classifyFilters(array $filters): array
    {
        // $filters can be a sequential (aggregation) or an associative (query) array
        $result = [];
        foreach ($filters as $key => $value) {
            if (isset($value) && $value !== '') {
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
                        $date_result = [
                            'floorField' => 'date_floor_year',
                            'ceilingField' => 'date_ceiling_year',
                        ];
                        if (array_key_exists('from', $value)) {
                            $date_result['startDate'] = $value['from'];
                        }
                        if (array_key_exists('to', $value)) {
                            $date_result['endDate'] = $value['to'];
                        }
                        $result['date_range'][] = $date_result;
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
                    case 'public':
                        if (is_int($key)) {
                            $result['boolean'][] = $value;
                        } else {
                            $result['boolean'][$key] = ($value === 1);
                        }
                        break;
                }
            }
        }
        return $result;
    }

    /**
     * @Route("/manuscripts/{id}", name="manuscript_show")
     * @Method("GET")
     * @param  int    $id manuscript id
     * @param Request $request
     */
    public function getManuscript(int $id, Request $request)
    {
        $manuscript = $this->get('manuscript_manager')->getManuscriptById($id);

        if (empty($manuscript)) {
            throw $this->createNotFoundException('There is no manuscript with the requested id.');
        }

        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            return new JsonResponse($manuscript->getJson());
        }
        return $this->render(
            'AppBundle:Manuscript:detail.html.twig',
            ['manuscript' => $manuscript]
        );
    }

    /**
     * @Route("/manuscripts/location", name="manuscripts_by_location")
     * @Method("POST")
     * @param Request $request
     */
    public function getManuscriptsByLocation(Request $request)
    {
        if (explode(',', $request->headers->get('Accept'))[0] == 'application/json') {
            try {
                $manuscripts = $this
                    ->get('manuscript_manager')
                    ->getManuscriptsByLocation(json_decode($request->getContent()));
            } catch (BadRequestHttpException $e) {
                return new JsonResponse(['error' => ['code' => 400, 'message' => $e->getMessage()]], 400);
            }
            return new JsonResponse(self::arrayToShortJson($manuscripts));
        }
        return  new \Exception('Not implemented.');
    }

    /**
     * @Route("/manuscripts/{id}", name="manuscript_put")
     * @Method("PUT")
     * @param  int    $id manuscript id
     * @param Request $request
     * @return JsonResponse
     */
    public function updateManuscript(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $manuscript = $this
            ->get('manuscript_manager')
            ->updateManuscript($id, json_decode($request->getContent()));

        if (empty($manuscript)) {
            throw $this->createNotFoundException('There is no manuscript with the requested id.');
        }

        $this->addFlash('success', 'Manuscript data successfully saved.');

        return new JsonResponse($manuscript->getJson());
    }

    /**
     * @Route("/manuscripts/{id}", name="manuscript_delete")
     * @Method("DELETE")
     * @param  int    $id manuscript id
     * @param Request $request
     * @return Response
     */
    public function deleteManuscript(int $id, Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        throw new \Exception('Not implemented');

        return new Response(null, 204);
    }

    /**
     * @Route("/manuscripts/{id}/edit", name="manuscript_edit")
     */
    public function editManuscript(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $manuscript = $this->get('manuscript_manager')->getManuscriptById($id);
        $locations = $this->get('location_manager')->getAllCitiesLibrariesCollections();
        $contents = self::arrayToShortJson($this->get('content_manager')->getAllContents());
        $patrons = self::arrayToShortJson($this->get('person_manager')->getAllPatrons());
        $scribes = self::arrayToShortJson($this->get('person_manager')->getAllSCribes());
        $relatedPersons = self::arrayToShortJson($this->get('person_manager')->getAllHistoricalPersons());
        $origins = self::arrayToShortJson($this->get('location_manager')->getAllOrigins());
        $books = self::arrayToShortJson($this->get('bibliography_manager')->getAllBooks());
        $articles = self::arrayToShortJson($this->get('bibliography_manager')->getAllArticles());
        $bookChapters = self::arrayToShortJson($this->get('bibliography_manager')->getAllBookChapters());
        $onlineSources = self::arrayToShortJson($this->get('bibliography_manager')->getAllOnlineSources());

        return $this->render(
            'AppBundle:Manuscript:edit.html.twig',
            [
                'id' => $id,
                'manuscript' => json_encode($manuscript->getJson()),
                'locations' => json_encode($locations),
                'contents' => json_encode($contents),
                'patrons' => json_encode($patrons),
                'scribes' => json_encode($scribes),
                'relatedPersons' => json_encode($relatedPersons),
                'origins' => json_encode($origins),
                'books' => json_encode($books),
                'articles' => json_encode($articles),
                'bookChapters' => json_encode($bookChapters),
                'onlineSources' => json_encode($onlineSources),
            ]
        );
    }

    // TODO: make it possible to create new manuscripts
}
