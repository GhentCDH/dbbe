<?php

namespace App\ElasticSearchService;

use Elastica\Aggregation;
use Elastica\Client;
use Elastica\Query;
use Elastica\Query\AbstractQuery;

class ElasticSearchService implements ElasticSearchServiceInterface
{
    private $client;
    private $indexPrefix;
    private $indexName;
    protected $type;
    protected $primaryIdentifiers;
    protected $roles;

    const MAX_AGG = 2147483647;
    const MAX_SEARCH = 10000;

    protected function __construct(
        array $config,
        string $indexPrefix,
        string $indexName,
        array $primaryIdentifiers = null,
        array $roles = null
    ) {
        $this->client = new Client($config);
        $this->indexPrefix = $indexPrefix;
        $this->indexName = $indexName;
        $this->index = $this->getIndex();
        $this->primaryIdentifiers = $primaryIdentifiers;
        $this->roles = $roles;
    }

    protected function getIndex()
    {
        return $this->client->getIndex($this->indexPrefix . '_'. $this->indexName);
    }

    protected function search(array $params = null): array
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

        // Track total number of hits
        $query->setTrackTotalHits();

        // Filtering
        if (isset($params['filters'])) {
            $query->setQuery(self::createQuery($params['filters']));
            $query->setHighlight(self::createHighlight($params['filters']));
        }

        $data = $this->index->search($query)->getResponse()->getData();

        // Format response
        $response = [
            'count' => $data['hits']['total']['value'],
            'data' => []
        ];

        // Build array to remove _stemmer or _original blow
        $rename = [];
        if (isset($params['filters']['text'])) {
            foreach ($params['filters']['text'] as $key => $value) {
                if (isset($value['field'])) {
                    $rename[$value['field']] = explode('_', $value['field'])[0];
                }
            }
        }
        if (isset($params['filters']['multiple_text'])) {
            foreach ($params['filters']['multiple_text'] as $multiple) {
                foreach ($multiple as $key => $value) {
                    if (isset($value['field'])) {
                        $rename[$value['field']] = explode('_', $value['field'])[0];
                    }
                }
            }
        }
        foreach ($data['hits']['hits'] as $result) {
            $part = $result['_source'];
            if (isset($result['highlight'])) {
                foreach ($result['highlight'] as $key => $value) {
                    $part['original_' . $key] = $part[$key];
                    $part[$key] = self::formatHighlight($value[0]);
                }
            }
            // Remove _stemmer or _original
            foreach ($rename as $key => $value) {
                if (isset($part[$key])) {
                    $part[$value] = $part[$key];
                    unset($part[$key]);
                }
                if (isset($part['original_' . $key])) {
                    $part['original_' . $value] = $part['original_' . $key];
                    unset($part['original_' . $key]);
                }
            }
            $response['data'][] = $part;
        }

        return $response;
    }

    protected function aggregate(array $fieldTypes, array $filterValues): array
    {
        $query = (new Query())
            ->setQuery(self::createQuery($filterValues, 'global'))
            // Only aggregation will be used
            ->setSize(0);

        foreach ($fieldTypes as $fieldType => $fieldNames) {
            switch ($fieldType) {
                case 'numeric':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Terms($fieldName))
                                ->setSize(self::MAX_AGG)
                                ->setField($fieldName)
                        );
                    }
                    break;
                case 'object':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Terms($fieldName))
                                ->setSize(self::MAX_AGG)
                                ->setField($fieldName . '.id')
                                ->addAggregation(
                                    (new Aggregation\Terms('name'))
                                        ->setField($fieldName . '.name.keyword')
                                )
                        );
                    }
                    break;
                case 'exact_text':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Terms($fieldName))
                                ->setSize(self::MAX_AGG)
                                ->setField($fieldName . '.keyword')
                        );
                    }
                    break;
                case 'nested':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Nested($fieldName, $fieldName))
                                ->addAggregation(
                                    (new Aggregation\Terms('id'))
                                        ->setSize(self::MAX_AGG)
                                        ->setField($fieldName . '.id')
                                        ->addAggregation(
                                            (new Aggregation\Terms('name'))
                                                ->setField($fieldName . '.name.keyword')
                                        )
                                )
                        );
                    }
                    break;
                case 'nested_multi':
                    foreach ($fieldNames as $fieldName) {
                        $filterQuery = self::createQuery($filterValues, $fieldName);
                        $query->addAggregation(
                            (new Aggregation\Filter($fieldName))
                            ->setFilter($filterQuery)
                            ->addAggregation(
                                (new Aggregation\Nested($fieldName, $fieldName))
                                    ->addAggregation(
                                        (new Aggregation\Terms('id'))
                                            ->setSize(self::MAX_AGG)
                                            ->setField($fieldName . '.id')
                                            ->addAggregation(
                                                (new Aggregation\Terms('name'))
                                                    ->setField($fieldName . '.name.keyword')
                                            )
                                    ->addAggregation(
                                        (new Aggregation\ReverseNested('reverse_nested'))
                                    )
                                )
                            )
                        );
                    }
                    break;
                case 'boolean':
                    foreach ($fieldNames as $fieldName) {
                        $query->addAggregation(
                            (new Aggregation\Terms($fieldName))
                                ->setSize(self::MAX_AGG)
                                ->setField($fieldName)
                        );
                    }
                    break;
                case 'multiple_fields_object':
                    // fieldName = [
                    //     [multiple_names] (e.g., [patron, scribe, related]),
                    //      'actual field name' (e.g. 'person'),
                    //      'dependend field name' (e.g. 'role')
                    //  ]
                    foreach ($fieldNames as $fieldName) {
                        foreach ($fieldName[0] as $key) {
                            $query->addAggregation(
                                (new Aggregation\Nested($key, $key))
                                    ->addAggregation(
                                        (new Aggregation\Terms('id'))
                                            ->setSize(self::MAX_AGG)
                                            ->setField($key . '.id')
                                            ->addAggregation(
                                                (new Aggregation\Terms('name'))
                                                    ->setField($key . '.name.keyword')
                                            )
                                    )
                            );
                        }
                    }
                    break;
                case 'multiple_fields_object_multi':
                    // fieldName = [
                    //     [multiple_names] (e.g., [patron, scribe, related]),
                    //      'actual field name' (e.g. 'person'),
                    //      'dependend field name' (e.g. 'role')
                    //  ]
                    foreach ($fieldNames as $fieldName) {
                        $filterQuery = self::createQuery($filterValues, $fieldName[1]);
                        foreach ($fieldName[0] as $key) {
                            $query->addAggregation(
                                (new Aggregation\Filter($key))
                                    ->setFilter($filterQuery)
                                    ->addAggregation(
                                        (new Aggregation\Nested($key, $key))
                                            ->addAggregation(
                                                (new Aggregation\Terms('id'))
                                                    ->setSize(self::MAX_AGG)
                                                    ->setField($key . '.id')
                                                    ->addAggregation(
                                                        (new Aggregation\Terms('name'))
                                                            ->setField($key . '.name.keyword')
                                                    )
                                            )
                                    )
                            );
                        }
                    }
                    break;
            }
        }

        $searchResult = $this->index->search($query);
        $results = [];
        foreach ($fieldTypes as $fieldType => $fieldNames) {
            switch ($fieldType) {
                case 'numeric':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $searchResult->getAggregation($fieldName);
                        foreach ($aggregation['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['key'],
                            ];
                        }
                    }
                    break;
                case 'object':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $searchResult->getAggregation($fieldName);
                        foreach ($aggregation['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['name']['buckets'][0]['key'],
                            ];
                        }
                    }
                    break;
                case 'exact_text':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $searchResult->getAggregation($fieldName);
                        foreach ($aggregation['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['key'],
                            ];
                        }
                    }
                    break;
                case 'nested':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $searchResult->getAggregation($fieldName);
                        foreach ($aggregation['id']['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['name']['buckets'][0]['key'],
                                'count' => $result['doc_count'],
                            ];
                        }
                    }
                    break;
                case 'nested_multi':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $searchResult->getAggregation($fieldName)[$fieldName];
                        foreach ($aggregation['id']['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['name']['buckets'][0]['key'],
                                'count' => $result['reverse_nested']['doc_count'],
                            ];
                        }
                    }
                    break;
                case 'boolean':
                    foreach ($fieldNames as $fieldName) {
                        $aggregation = $searchResult->getAggregation($fieldName);
                        foreach ($aggregation['buckets'] as $result) {
                            $results[$fieldName][] = [
                                'id' => $result['key'],
                                'name' => $result['key_as_string'],
                            ];
                        }
                    }
                    break;
                case 'multiple_fields_object':
                    foreach ($fieldNames as $fieldName) {
                        // fieldName = [
                        //     [multiple_names] (e.g., [patron, scribe, related]),
                        //      'actual field name' (e.g. 'person'),
                        //      'dependent field name' (e.g. 'role')
                        //  ]

                        //  a filter is set for the actual field name
                        if (isset($filterValues[$fieldType][$fieldName[1]])) {
                            $ids = [];
                            foreach ($fieldName[0] as $key) {
                                $aggregation = $searchResult->getAggregation($key);
                                foreach ($aggregation['id']['buckets'] as $result) {
                                    if (!in_array($result['key'], $ids)) {
                                        $ids[] = $result['key'];
                                        $results[$fieldName[1]][] = [
                                            'id' => $result['key'],
                                            'name' => $result['name']['buckets'][0]['key'],
                                        ];
                                    }

                                    // check if this result is a result of the actual field filter
                                    if ($result['key'] == $filterValues[$fieldType][$fieldName[1]][1]) {
                                        $results[$fieldName[2]][] = [
                                            'id' => $key,
                                            'name' => $this->roles[str_replace('_public', '', $key)]->getName(),
                                            'count' => $result['doc_count'],
                                        ];
                                    }
                                }
                            }
                        } else {
                            // prevent duplicate entries
                            $ids = [];
                            foreach ($fieldName[0] as $key) {
                                $aggregation = $searchResult->getAggregation($key);
                                foreach ($aggregation['id']['buckets'] as $result) {
                                    if (!in_array($result['key'], $ids)) {
                                        $ids[] = $result['key'];
                                        $results[$fieldName[1]][] = [
                                            'id' => $result['key'],
                                            'name' => $result['name']['buckets'][0]['key'],
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    break;
                case 'multiple_fields_object_multi':
                    foreach ($fieldNames as $fieldName) {
                        // fieldName = [
                        //     [multiple_names] (e.g., [patron, scribe, related]),
                        //      'actual field name' (e.g. 'person'),
                        //      'dependent field name' (e.g. 'role')
                        //  ]

                        //  a filter is set for the actual field name
                        if (isset($filterValues[$fieldType][$fieldName[1]])) {
                            $ids = [];
                            $depAggs = [];
                            foreach ($fieldName[0] as $key) {
                                $aggregation = $searchResult->getAggregation($key)[$key];
                                foreach ($aggregation['id']['buckets'] as $result) {
                                    if (!in_array($result['key'], $ids)) {
                                        $ids[] = $result['key'];
                                        $results[$fieldName[1]][] = [
                                            'id' => $result['key'],
                                            'name' => $result['name']['buckets'][0]['key'],
                                            'count' => $result['doc_count'],
                                        ];
                                    }

                                    // check if this result is a result of the actual field filter
                                    if (in_array($result['key'], $filterValues[$fieldType][$fieldName[1]][1])) {
                                        if (array_key_exists($key, $depAggs)) {
                                            $depAggs[$key]['count'] += $result['doc_count'];
                                        } else {
                                            $depAggs[$key] = [
                                                'id' => $key,
                                                'name' => $this->roles[str_replace('_public', '', $key)]->getName(),
                                                'count' => $result['doc_count'],
                                            ];
                                        }
                                    }
                                }
                            }
                            $results[$fieldName[2]] = array_values($depAggs);
                        } else {
                            // prevent duplicate entries
                            $ids = [];
                            foreach ($fieldName[0] as $key) {
                                $aggregation = $searchResult->getAggregation($key)[$key];
                                foreach ($aggregation['id']['buckets'] as $result) {
                                    if (!in_array($result['key'], $ids)) {
                                        $ids[] = $result['key'];
                                        $results[$fieldName[1]][] = [
                                            'id' => $result['key'],
                                            'name' => $result['name']['buckets'][0]['key'],
                                            'count' => $result['doc_count'],
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    break;
            }
        }

        return $results;
    }

    protected static function createQuery(array $filterTypes, string $aggregateKey = null): Query\BoolQuery
    {
        $filterQuery = new Query\BoolQuery();
        foreach ($filterTypes as $filterType => $filterValues) {
            switch ($filterType) {
                case 'numeric':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addFilter(
                            new Query\Match($key, $value)
                        );
                    }
                    break;
                case 'object':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $key => $value) {
                        // If value == -1, select all entries without a value for a specific field
                        if ($value == -1) {
                            $filterQuery->addMustNot(
                                new Query\Exists($key)
                            );
                        } else {
                            $filterQuery->addFilter(
                                new Query\Match($key . '.id', $value)
                            );
                        }
                    }
                    break;
                case 'date_range':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $value) {
                        // If type is not set, us broad match (backward compatibility)
                        // The data interval must exactly match the search interval
                        if (isset($value['type']) && $value['type'] == 'exact') {
                            if (isset($value['startDate'])) {
                                $filterQuery->addFilter(
                                    new Query\Match($value['floorField'], $value['startDate'])
                                );
                            }
                            if (isset($value['endDate'])) {
                                $filterQuery->addFilter(
                                    new Query\Match($value['ceilingField'], $value['endDate'])
                                );
                            }
                        }
                        // The data interval must be included in the search interval
                        // If only start or end: exact match with start or end
                        // range must be between floor and ceiling
                        if (isset($value['type']) && $value['type'] == 'included') {
                            if (isset($value['startDate']) && !isset($value['endDate'])) {
                                $filterQuery->addFilter(
                                    new Query\Match($value['floorField'], $value['startDate'])
                                );
                            } elseif (isset($value['endDate']) && !isset($value['startDate'])) {
                                $filterQuery->addFilter(
                                    new Query\Match($value['ceilingField'], $value['endDate'])
                                );
                            } else {
                                $filterQuery->addFilter(
                                    (new Query\Range())
                                        ->addField($value['floorField'], ['gte' => $value['startDate']])
                                );
                                $filterQuery->addFilter(
                                    (new Query\Range())
                                        ->addField($value['ceilingField'], ['lte' => $value['endDate']])
                                );
                            }
                        }
                        // The data interval must include the search interval
                        // If only start or end: exact match with start or end
                        // range must be between floor and ceiling
                        if (isset($value['type']) && $value['type'] == 'include') {
                            if (isset($value['startDate']) && !isset($value['endDate'])) {
                                $filterQuery->addFilter(
                                    new Query\Match($value['floorField'], $value['startDate'])
                                );
                            } elseif (isset($value['endDate']) && !isset($value['startDate'])) {
                                $filterQuery->addFilter(
                                    new Query\Match($value['ceilingField'], $value['endDate'])
                                );
                            } else {
                                $filterQuery->addFilter(
                                    (new Query\Range())
                                        ->addField($value['floorField'], ['lte' => $value['startDate']])
                                );
                                $filterQuery->addFilter(
                                    (new Query\Range())
                                        ->addField($value['ceilingField'], ['gte' => $value['endDate']])
                                );
                            }
                        }
                        // The data interval must overlap with the search interval
                        // floor or ceiling must be within range, or range must be between floor and ceiling
                        else {
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
                                            ->addFilter(
                                                (new Query\Range())
                                                    ->addField($value['floorField'], ['lte' => $value['startDate']])
                                            )
                                            ->addFilter(
                                                (new Query\Range())
                                                    ->addField($value['ceilingField'], ['gte' => $value['endDate']])
                                            )
                                    );
                            }
                            $filterQuery->addFilter(
                                $subQuery
                            );
                        }
                    }
                    break;
                case 'nested':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $key => $value) {
                        // If value == -1, select all entries without a value for a specific field
                        if ($value == -1) {
                            $filterQuery->addMustNot(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addFilter(new Query\Exists($key))
                                    )
                            );
                        } else {
                            $filterQuery->addFilter(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addFilter(
                                                new Query\Match($key . '.id', $value)
                                            )
                                    )
                            );
                        }
                    }
                    break;
                case 'nested_multi':
                    // Don't include in global aggregation query
                    if ($aggregateKey == 'global') {
                        break;
                    }
                    foreach ($filterValues as $fieldName => $values) {
                        // Don't include in the aggregation query for the field itself
                        if ($aggregateKey == $fieldName) {
                            break;
                        }

                        if (
                            array_key_exists('nested_multi_op', $filterTypes)
                            && array_key_exists($fieldName . '_op', $filterTypes['nested_multi_op'])
                            && $filterTypes['nested_multi_op'][$fieldName . '_op'] == 'and'
                        ) {
                            // and
                            foreach ($values as $value) {
                                if ($value == -1) {
                                    $filterQuery->addFilter(
                                        (new Query\Nested())
                                            ->setPath($fieldName)
                                            ->setQuery(
                                                (new Query\BoolQuery())
                                                    ->addMustNot(new Query\Exists($fieldName))
                                            )
                                    );
                                } else {
                                    $filterQuery->addFilter(
                                        (new Query\Nested())
                                            ->setPath($fieldName)
                                            ->setQuery(
                                                (new Query\BoolQuery())
                                                    ->addFilter(
                                                        new Query\Match($fieldName . '.id', $value)
                                                    )
                                            )
                                    );
                                }
                            }
                        } else {
                            // or (default)
                            $subQuery = new Query\BoolQuery();
                            foreach ($values as $value) {
                                if ($value == -1) {
                                    $subQuery->addShould(
                                        (new Query\BoolQuery())
                                            ->addMustNot(new Query\Exists($fieldName))
                                    );
                                } else {
                                    $subQuery->addShould(['match' => [$fieldName . '.id' => $value]]);
                                }
                            }
                            $filterQuery->addFilter(
                                (new Query\Nested())
                                    ->setPath($fieldName)
                                    ->setQuery($subQuery)
                            );
                        }
                    }
                    break;
                case 'nested_toggle':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $key => $value) {
                        // value = [actual value, include/exclude]
                        if (!$value[1]) {
                            // management collection not present
                            // no management collections present or only other management collections present
                            $filterQuery->addFilter(
                                (new Query\BoolQuery())
                                    ->addShould(
                                        (new Query\BoolQuery())
                                            ->addMustNot(
                                                (new Query\Nested())
                                                    ->setPath($key)
                                                    ->setQuery(
                                                        (new Query\BoolQuery())
                                                            ->addFilter(new Query\Exists($key))
                                                    )
                                            )
                                    )
                                    ->addShould(
                                        (new Query\BoolQuery())
                                            ->addMustNot(
                                                (new Query\Nested())
                                                    ->setPath($key)
                                                    ->setQuery(
                                                        (new Query\BoolQuery())
                                                            ->addFilter(
                                                                new Query\Match($key . '.id', $value[0])
                                                            )
                                                    )
                                            )
                                    )
                            );
                        } else {
                            // management collection present
                            $filterQuery->addFilter(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addFilter(
                                                new Query\Match($key . '.id', $value[0])
                                            )
                                    )
                            );
                        }
                    }
                    break;
                case 'text':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addMust(self::constructTextQuery($key, $value));
                    }
                    break;
                case 'multiple_text':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $field => $options) {
                        $subQuery = new Query\BoolQuery();
                        foreach ($options as $key => $value) {
                            $subQuery->addShould(self::constructTextQuery($key, $value));
                        }
                        $filterQuery->addMust($subQuery);
                    }
                    break;
                case 'exact_text':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $key => $value) {
                        if ($value == -1) {
                            $filterQuery->addMustNot(
                                new Query\Exists($key)
                            );
                        } else {
                            $filterQuery->addFilter(
                                (new Query\Match($key . '.keyword', $value))
                            );
                        }
                    }
                    break;
                case 'boolean':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addFilter(
                            (new Query\Match($key, $value))
                        );
                    }
                    break;
                case 'multiple_fields_object':
                    // Include in global aggregation query and not in field aggregation queries
                    if ($aggregateKey != null && $aggregateKey != 'global') {
                        break;
                    }
                    // options = [[keys], value]
                    foreach ($filterValues as $key => $options) {
                        [$keys, $value] = $options;
                        $subQuery = new Query\BoolQuery();
                        foreach ($keys as $key) {
                            $subQuery->addShould(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addFilter(
                                                new Query\Match($key . '.id', $value)
                                            )
                                    )
                            );
                        }
                        $filterQuery->addFilter($subQuery);
                    }
                    break;
                case 'multiple_fields_object_multi':
                    // Don't include in global aggregation query
                    if ($aggregateKey == 'global') {
                        break;
                    }
                    // options = [[keys], values]
                    foreach ($filterValues as $fieldName => $options) {
                        // Don't include in the aggregation query for the field itself
                        if ($aggregateKey == $fieldName) {
                            break;
                        }
                        [$keys, $values] = $options;

                        $subQuery = new Query\BoolQuery();
                        foreach ($keys as $key) {
                            $subSubQuery = new Query\BoolQuery();
                            foreach ($values as $value) {
                                $subSubQuery->addShould(['match' => [$key . '.id' => $value]]);
                            }
                            $subQuery->addShould(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery($subSubQuery)
                            );
                        }
                        $filterQuery->addFilter($subQuery);
                    }
                    break;
            }
        }
        return $filterQuery;
    }

    protected function getIdentifierSystemNames(): array
    {
        return array_map(
            function ($identifier) {
                return $identifier->getSystemName();
            },
            $this->primaryIdentifiers
        );
    }

    protected function getRoleSystemNames(bool $viewInternal): array
    {
        return array_map(
            function ($role) use ($viewInternal) {
                return $viewInternal ? $role->getSystemName() : $role->getSystemName() . '_public';
            },
            $this->roles
        );
    }

    protected static function createHighlight(array $filterTypes): array
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
                        $field = $value['field'] ?? $key;
                        $highlights['fields'][$field] = new \stdClass();
                    }
                    break;
                case 'multiple_text':
                    foreach ($filterValues as $options) {
                        foreach ($options as $key => $value) {
                            $field = $value['field'] ?? $key;
                            $highlights['fields'][$field] = new \stdClass();
                        }
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
        foreach ($lines as $number => $line) {
            // Remove \r
            $line = trim($line);
            // Each word is marked separately, so we only need the lines with <mark> in them
            if (strpos($line, '<mark>') !== false) {
                $result[$number] = $line;
            }
        }
        return $result;
    }

    /**
     * Construct a text query
     * @param  string         $key   Elasticsearch field to match (unless $value['field']) is provided
     * @param  array          $value Array with [combination] of match (any, all, phrase), the [text] to search for and optionally the [field] to search in (if not provided, $key is used)
     * @return AbstractQuery
     */
    protected static function constructTextQuery($key, $value): AbstractQuery
    {
        // Verse initialization
        if (isset($value['init']) && $value['init']) {
            return new Query\MatchPhrase($key, $value['text']);
        }

        $field = $value['field'] ?? $key;
        // Replace multiple spaces with a single space
        $text = preg_replace('!\s+!', ' ', $value['text']);

        // Remove colons
        $text = str_replace(':', '', $text);

        // Check if user does not use advanced syntax
        if (preg_match('/AND|OR|[\/~\-"()]/', $text) === 0) {
            if ($value['combination'] == 'phrase') {
                if (preg_match('/[*?]/', $text) === 0) {
                    $text = '"' . $text . '"';
                } else {
                    $text = implode(' AND ', explode(' ', $text));
                }
            } elseif ($value['combination'] == 'all') {
                $text = implode(' AND ', explode(' ', $text));
            }
        }

        return (new Query\QueryString($text))->setDefaultField($field);
    }

    protected function normalizeString(string $input): string
    {
        $result = $input;

        // Get wildcard character position and remove wildcards
        // question mark
        $qPos = [];
        $lastPos = 0;
        while (($lastPos = strpos($result, '?', $lastPos))!== false) {
            $qPos[] = $lastPos;
            $lastPos = $lastPos + strlen('*');
        }
        $result = str_replace('?', '', $result);
        // asterisk
        $aPos = [];
        $lastPos = 0;
        while (($lastPos = strpos($result, '*', $lastPos))!== false) {
            $aPos[] = $lastPos;
            $lastPos = $lastPos + strlen('*');
        }
        $result = str_replace('*', '', $result);

        $normalizedArray = $this->index->analyze(
            [
                'analyzer' => 'custom_greek_original',
                'text' => $result,
            ]
        );
        $normalizedTokens = [];
        foreach ($normalizedArray as $token) {
            $normalizedTokens[] = $token['token'];
        }
        $result = implode(' ', $normalizedTokens);

        // Reinsert wildcards
        foreach ($aPos as $a) {
            $result = substr_replace($result, '*', $a, 0);
        }
        foreach ($qPos as $q) {
            $result = substr_replace($result, '?', $q, 0);
        }

        return $result;
    }
}
