<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Aggregation;
use Elastica\Client;
use Elastica\Document;
use Elastica\Type;
use Elastica\Query;

const MAX = 2147483647;

class ElasticSearchService implements ElasticSearchServiceInterface
{
    private $client;
    private $indexPrefix;
    protected $type;

    public function __construct(array $config, string $indexPrefix)
    {
        $this->client = new Client($config);
        $this->indexPrefix = $indexPrefix;
    }

    public function getIndex($indexName)
    {
        return $this->client->getIndex($this->indexPrefix . '_'. $indexName);
    }

    protected function bulkAdd(array $indexingContents)
    {
        $bulk_documents = [];
        while (count($indexingContents) > 0) {
            $bulk_contents = array_splice($indexingContents, 0, 500);
            foreach ($bulk_contents as $bc) {
                $bulk_documents[] = new Document($bc['id'], $bc);
            }
            $this->type->addDocuments($bulk_documents);
            $bulk_documents = [];
        }
        $this->type->getIndex()->refresh();
    }

    protected function add(array $indexingContent)
    {
        $document = new Document($indexingContent['id'], $indexingContent);
        $this->type->addDocument($document);
    }

    protected function del(int $id)
    {
        $document = new Document($id, []);
        $this->type->deleteDocument($document);
    }

    public function search(array $params = null): array
    {
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
            $query->setHighlight(self::createHighlight($params['filters']));
        }

        $data = $this->type->search($query)->getResponse()->getData();

        // Format response
        $response = [
            'count' => $data['hits']['total'],
            'data' => []
        ];
        foreach ($data['hits']['hits'] as $result) {
            $part = $result['_source'];
            if (isset($result['highlight'])) {
                foreach ($result['highlight'] as $key => $value) {
                    $part[$key] = self::formatHighlight($value[0]);
                }
            }
            $response['data'][] = $part;
        }
        return $response;
    }

    public function aggregate(array $fieldTypes, array $filterValues): array
    {
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
                case 'boolean':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Terms($fieldName))
                                ->setSize(MAX)
                                ->setField($fieldName)
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
                        $aggregation = $this->type->search($query)->getAggregation($fieldName);
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
                        $aggregation = $this->type->search($query)->getAggregation($fieldName);
                        foreach ($aggregation['id']['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['name']['buckets'][0]['key'],
                            ];
                        }
                    }
                    break;
                case 'boolean':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $this->type->search($query)->getAggregation($fieldName);
                        foreach ($aggregation['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['key_as_string'],
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
                            $filterQuery->addMustNot(
                                new Query\Exists($key)
                            );
                        } else {
                            $filterQuery->addMust(
                                new Query\Match($key . '.id', $value)
                            );
                        }
                    }
                    break;
                case 'date_range':
                    foreach ($filterValues as $value) {
                        // floor or ceiling must be within range, or range must be between floor and ceiling
                        // the value in this case will be a two-dimentsional array with
                        // * in the first row the floor and/or ceiling field names
                        // * in the second row the range min and/or max values
                        $args = [];
                        if (isset($value['startDate'])) {
                            $args['gte'] = $value['startDate'];
                        }
                        if (isset($value['endDate'])) {
                            $args['lte'] = $value['endDate'];
                        }
                        $subQuery = (new Query\BoolQuery())
                            // floor
                            ->addShould(
                                (new Query\Range())
                                    ->addField(
                                        $value['floorField'],
                                        $args
                                    )
                            )
                            // ceiling
                            ->addShould(
                                (new Query\Range())
                                    ->addField(
                                        $value['ceilingField'],
                                        $args
                                    )
                            );
                        if (isset($value['startDate']) && isset($value['endDate'])) {
                            $subQuery
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
                                );
                        }
                        $filterQuery->addMust(
                            $subQuery
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
                            (new Query\Match($key, $value))
                        );
                    }
                    break;
                case 'exact_text':
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addMust(
                            (new Query\Match($key . '.keyword', $value))
                        );
                    }
                    break;
                case 'boolean':
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addMust(
                            (new Query\Match($key, $value))
                        );
                    }
                    break;
            }
        }
        return $filterQuery;
    }

    private static function createHighlight(array $filterTypes): array
    {
        $highlights = [
            'number_of_fragments' => 0,
            'pre_tags' => ['<mark>'],
            'post_tags' => ['</mark>'],
            'fields' => [],
        ];
        foreach ($filterTypes as $filterType => $filterValues) {
            switch ($filterType) {
                case 'text':
                    foreach ($filterValues as $key => $value) {
                        $highlights['fields'][$key] = new \stdClass();
                    }
                    break;
            }
        }
        return $highlights;
    }

    private static function formatHighlight(string $highlight): array
    {
        $lines = explode(PHP_EOL, html_entity_decode($highlight));
        $result = [];
        foreach ($lines as $line) {
            // Remove \r
            $line = trim($line);
            if (strpos($line, '<mark>') !== false) {
                $result[] = $line;
            }
        }
        return $result;
    }
}
