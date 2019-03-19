<?php

namespace AppBundle\Service\ElasticSearchService;

use stdClass;

use Elastica\Document;
use Elastica\Type;

use AppBundle\Model\IdElasticInterface;

abstract class ElasticEntityService extends ElasticBaseService
{
    abstract public function classifyAggregationFilters(array $filters, bool $viewInternal): array;
    abstract public function classifySearchFilters(array $filters, bool $viewInternal): array;

    /**
     * Get the ids of all entities satisfying a certain filter.
     * @param  stdClass $filter
     * @return array
     */
    public function getAllResults(stdClass $filter): array
    {
        $params = [
            'limit' => 1000,
            'page' => 0,
        ];
        $params['filters'] = $this->classifySearchFilters(json_decode(json_encode($filter), true), true);
        $params['sort'] = ['id' => 'asc'];

        $ids = [];
        do {
            $params['page']++;
            $results = $this->search($params);
            foreach ($results['data'] as $result) {
                $ids[] = $result['id'];
            }
        } while ($params['page'] * $params['limit'] < $results['count']);

        return $ids;
    }
}
