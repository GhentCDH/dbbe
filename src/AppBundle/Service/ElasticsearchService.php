<?php

namespace AppBundle\Service;

use Elastica\Aggregation\Terms;
use Elastica\Client;
use Elastica\Document;
use Elastica\Type;
use Elastica\Type\Mapping;
use Elastica\Query;
use Elastica\Suggest\Completion;

const INDEX_PREFIX = "dbbe_";
const MAX = 2147483647;

class ElasticsearchService
{
    protected $client;

    public function __construct($hosts)
    {
        $this->client = new Client($hosts);
    }

    public function getIndex($indexName)
    {
        return $this->client->getIndex(INDEX_PREFIX . $indexName);
    }

    public function resetIndex($indexName)
    {
        $index = $this->getIndex($indexName);
        if ($index->exists()) {
            $index->delete();
        }
        $index->create();
    }

    protected function bulkAdd(Type $type, array $indexing_contents)
    {
        $bulk_documents = [];
        while (count($indexing_contents) > 0) {
            $bulk_contents = array_splice($indexing_contents, 0, 500);
            foreach ($bulk_contents as $bc) {
                $bulk_documents[] = new Document($bc['id'], $bc);
            }
            $type->addDocuments($bulk_documents);
            $bulk_documents = [];
        }
        $type->getIndex()->refresh();
    }

    public function addManuscripts(array $manuscripts)
    {
        $index = $this->getIndex('documents')->getType('manuscript');

        $this->bulkAdd($type, $manuscripts);
    }

    public function search(string $indexName, string $typeName, array $params = null): array
    {
        $type = $this->getIndex($indexName)->getType($typeName);

        // Construct query
        $query = new Query();
        // Number of results
        if (isset($params['limit']) && is_numeric($params['limit'])) {
            $query->setSize($params['limit']);
        }

        // Pagination
        if (
            isset($params['page']) && is_numeric($params['page']) &&
            isset($params['limit']) && is_numeric($params['limit'])
        ) {
            $query->setFrom(($params['page'] - 1) * $params['limit']);
        }

        // Sorting
        if (isset($params['orderBy'])) {
            if (isset($params['ascending']) && $params['ascending'] == 0) {
                $order = 'desc';
            } else {
                $order = 'asc';
            }
            $sort = [];
            foreach ($params['orderBy'] as $field) {
                $sort[] = [$field => $order];
            }
            $query->setSort($sort);
        }

        // Filtering
        if (isset($params['filters'])) {
            $filterQuery = new Query\BoolQuery();
            foreach ($params['filters'] as $key => $value) {
                $filterQuery->addMust(['match' => [$key => $value]]);
            }
            $query->setQuery($filterQuery);
        }

        $data = $type->search($query)->getResponse()->getData();

        // Format response
        $response = [
            'count' => $data['hits']['total'],
            'data' => []
        ];
        foreach ($data['hits']['hits'] as $result) {
            $response['data'][] = $result['_source'];
        }
        return $response;
    }

    public function aggregate(string $indexName, string $typeName, string $field, array $preselected = []): array
    {
        $type = $this->getIndex($indexName)->getType($typeName);

        // use keyword field if available
        $aggregationField = $field;
        if (isset($type->getMapping()[$typeName]['properties'][$field]['fields']['keyword'])) {
            $aggregationField .= '.keyword';
        }

        // Construct query
        $query = new Query();

        // Add aggregation part
        $agg = new Terms($field);
        $agg->setField($aggregationField);
        $agg->setSize(MAX);
        $query->addAggregation($agg);

        // Add preselected query
        if (count($preselected) > 0) {
            $filterQuery = new Query\BoolQuery();
            foreach ($preselected as $key => $value) {
                $filterQuery->addShould(['match' => [$key => $value]]);
            }
            $query->setQuery($filterQuery);
        }

        $buckets = $type->search($query)->getAggregation($field);

        $results = [];
        foreach ($buckets['buckets'] as $bucket) {
            $results[$bucket['key']] = $bucket['doc_count'];
        }

        return $results;
    }
}
