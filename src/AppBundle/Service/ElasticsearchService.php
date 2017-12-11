<?php

namespace AppBundle\Service;

use Elastica\Client;
use Elastica\Document;
use Elastica\Query;

class ElasticsearchService
{
    protected $client;
    protected $index;

    public function __construct($hosts, $index)
    {
        $this->client = new Client($hosts);
        $this->index = $this->client->getIndex($index);
    }

    public function deleteIndex()
    {
        if ($this->index->exists()) {
            $this->index->delete();
        }
    }

    public function addToIndex(string $typeName, array $manuscripts)
    {
        $type = $this->index->getType($typeName);
        $documents = [];
        foreach ($manuscripts as $manuscript) {
            $documents[] = new Document($manuscript['id'], $manuscript);

            // Bulk index each 500 documents
            if (count($documents == 500)) {
                $type->addDocuments($documents);
                $documents = [];
            }
        }
        // Bulk index the rest of the documents
        if (count($documents) > 0) {
            $type->addDocuments($documents);
        }
        $this->index->refresh();
    }

    // TODO: process params
    public function search(string $type = null, array $params = null): array
    {
        // Define query object (index or single type)
        if (isset($type)) {
            $query_object = $this->index->getType($type);
        } else {
            $query_object = $this->index;
        }

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
                $filterQuery->addShould(['term' => [$key => strtolower($value)]]);
            }
            $query->setQuery($filterQuery);
        }

        $data = $query_object->search($query)->getResponse()->getData();

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
}
