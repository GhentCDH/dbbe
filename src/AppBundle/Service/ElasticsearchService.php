<?php

namespace AppBundle\Service;

use Elastica\Aggregation;
use Elastica\Client;
use Elastica\Document;
use Elastica\Type;
use Elastica\Query;

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
        $type = $this->getIndex('documents')->getType('manuscript');

        $mapping = new Type\Mapping;
        $mapping->setType($type);

        $mapping->setProperties(
            [
                'content' => ['type' => 'nested'],
                'patron' => ['type' => 'nested'],
                'scribe' => ['type' => 'nested'],
                'origin' => ['type' => 'nested'],
            ]
        );
        $mapping->send();

        $manuscriptsElastic = [];
        foreach ($manuscripts as $manuscript) {
            $manuscriptsElastic [] = $manuscript->getElastic();
        }

        $this->bulkAdd($type, $manuscriptsElastic);
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
        if (isset($params['page']) && is_numeric($params['page']) &&
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
            $query->setQuery(self::createQuery($params['filters']));
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

    public function aggregate(string $indexName, string $typeName, array $fieldTypes, array $filterValues): array
    {
        $type = $this->getIndex($indexName)->getType($typeName);

        $query = (new Query())
            ->setQuery(self::createQuery($filterValues));

        foreach ($fieldTypes as $fieldType => $fieldNames) {
            switch ($fieldType) {
                case 'object':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Terms($fieldName))
                                ->setSize(MAX)
                                ->setField($fieldName . '.id')
                                ->addAggregation(
                                    (new Aggregation\Terms('name'))
                                        ->setField($fieldName . '.name.keyword')
                                )
                        );
                    }
                    break;
                case 'nested':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Nested($fieldName, $fieldName))
                                ->addAggregation(
                                    (new Aggregation\Terms('id'))
                                        ->setSize(MAX)
                                        ->setField($fieldName . '.id')
                                        ->addAggregation(
                                            (new Aggregation\Terms('name'))
                                                ->setField($fieldName . '.name.keyword')
                                        )
                                )
                        );
                    }
                    break;
            }
        }

        $results = [];
        foreach ($fieldTypes as $fieldType => $fieldNames) {
            switch ($fieldType) {
                case 'object':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $type->search($query)->getAggregation($fieldName);
                        foreach ($aggregation['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['name']['buckets'][0]['key'],
                            ];
                        }
                    }
                    break;
                case 'nested':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $type->search($query)->getAggregation($fieldName);
                        foreach ($aggregation['id']['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['name']['buckets'][0]['key'],
                            ];
                        }
                    }
                    break;
            }
        }

        return $results;
    }

    private static function createQuery(array $filterTypes): Query\BoolQuery
    {
        $filterQuery = new Query\BoolQuery();
        foreach ($filterTypes as $filterType => $filterValues) {
            switch ($filterType) {
                case 'object':
                    foreach ($filterValues as $key => $value) {
                        // If value == -1, select all entries without a value for a specific field
                        if ($value == -1) {
                            $filterQuery->addMustNot(new Query\Exists($key));
                        } else {
                            $filterQuery->addMust(
                                (new Query\Match($key . '.id', $value))
                            );
                        }
                    }
                    break;
                case 'date_range':
                    foreach ($filterValues as $value) {
                        // floor or ceiling must be within range, or range must be between floor and ceiling
                        // the value in this case will be a two-dimentsional array with
                        // * in the first row the floor and ceiling field names
                        // * in the second row the range min and max values
                        $filterQuery->addMust(
                            (new Query\BoolQuery())
                                // floor
                                ->addShould(
                                    (new Query\Range())
                                        ->addField(
                                            $value['floorField'],
                                            ['gte' => $value['startDate'], 'lte' => $value['endDate']]
                                        )
                                )
                                // ceiling
                                ->addShould(
                                    (new Query\Range())
                                        ->addField(
                                            $value['ceilingField'],
                                            ['gte' => $value['startDate'], 'lte' => $value['endDate']]
                                        )
                                )
                                // between floor and ceiling
                                ->addShould(
                                    (new Query\BoolQuery())
                                        ->addMust(
                                            (new Query\Range())
                                                ->addField($value['floorField'], ['lte' => $value['startDate']])
                                        )
                                        ->addMust(
                                            (new Query\Range())
                                                ->addField($value['ceilingField'], ['gte' => $value['endDate']])
                                        )
                                )
                        );
                    }
                    break;
                case 'nested':
                    foreach ($filterValues as $key => $value) {
                        // If value == -1, select all entries without a value for a specific field
                        $subQuery = new Query\BoolQuery();
                        if ($value == -1) {
                            $subQuery->addMustNot(new Query\Exists($key));
                        } else {
                            $subQuery->addMust(['match' => [$key . '.id' => $value]]);
                        }
                        $filterQuery->addMust(
                            (new Query\Nested())
                                ->setPath($key)
                                ->setQuery($subQuery)
                        );
                    }
                    break;
                case 'text':
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addMust(
                            (new Query\Match($key . '.keyword', $value))
                        );
                    }
                    break;
            }
        }
        return $filterQuery;
    }
}
