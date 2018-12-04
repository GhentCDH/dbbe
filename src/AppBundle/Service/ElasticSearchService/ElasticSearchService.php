<?php

namespace AppBundle\Service\ElasticSearchService;

use Normalizer;

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
        string $typeName,
        array $primaryIdentifiers = null,
        array $roles = null
    ) {
        $this->client = new Client($config);
        $this->indexPrefix = $indexPrefix;
        $this->indexName = $indexName;
        $this->type = $this->getIndex()->getType($typeName);
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
            ->setQuery(self::createQuery($filterValues))
            // Only aggregation will be used
            ->setSize(0);

        foreach ($fieldTypes as $fieldType => $fieldNames) {
            switch ($fieldType) {
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
            }
        }

        $searchResult = $this->type->search($query);
        $results = [];
        foreach ($fieldTypes as $fieldType => $fieldNames) {
            switch ($fieldType) {
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
                        if (isset($filterValues['multiple_fields_object'][$fieldName[1]])) {
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
                                    if ($result['key'] == $filterValues['multiple_fields_object'][$fieldName[1]][1]) {
                                        $results[$fieldName[2]][] = [
                                            'id' => $key,
                                            'name' => $this->roles[$key]->getName() . ' (' . $result['doc_count'] . ')',
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
            }
        }

        return $results;
    }

    protected static function createQuery(array $filterTypes): Query\BoolQuery
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
                        if ($value == -1) {
                            $filterQuery->addMustNot(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addMust(new Query\Exists($key))
                                    )
                            );
                        } else {
                            $filterQuery->addMust(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addMust(['match' => [$key . '.id' => $value]])
                                    )
                            );
                        }
                    }
                    break;
                case 'nested_toggle':
                    foreach ($filterValues as $key => $value) {
                        // value = [actual value, include/exclude]
                        if (!$value[1]) {
                            // management collection not present
                            // no management collections present or only other management collections present
                            $filterQuery->addMust(
                                (new Query\BoolQuery())
                                    ->addShould(
                                        (new Query\BoolQuery())
                                            ->addMustNot(
                                                (new Query\Nested())
                                                    ->setPath($key)
                                                    ->setQuery(
                                                        (new Query\BoolQuery())
                                                            ->addMust(new Query\Exists($key))
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
                                                            ->addMust(['match' => [$key . '.id' => $value[0]]])
                                                    )
                                            )
                                    )
                            );
                        } else {
                            // management collection present
                            $filterQuery->addMust(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addMust(['match' => [$key . '.id' => $value[0]]])
                                    )
                            );
                        }
                    }
                    break;
                case 'text':
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addMust(self::constructTextQuery($key, $value));
                    }
                    break;
                case 'multiple_text':
                    foreach ($filterValues as $field => $options) {
                        $subQuery = new Query\BoolQuery();
                        foreach ($options as $key => $value) {
                            $subQuery->addShould(self::constructTextQuery($key, $value));
                        }
                        $filterQuery->addMust($subQuery);
                    }
                    break;
                case 'exact_text':
                    foreach ($filterValues as $key => $value) {
                        if ($value == -1) {
                            $filterQuery->addMustNot(
                                new Query\Exists($key)
                            );
                        } else {
                            $filterQuery->addMust(
                                (new Query\Match($key . '.keyword', $value))
                            );
                        }
                    }
                    break;
                case 'boolean':
                    foreach ($filterValues as $key => $value) {
                        $filterQuery->addMust(
                            (new Query\Match($key, $value))
                        );
                    }
                    break;
                case 'multiple_fields_object':
                    // options = [[keys], value]
                    foreach ($filterValues as $key => $options) {
                        $subQuery = new Query\BoolQuery();
                        foreach ($options[0] as $key) {
                            $subQuery->addShould(
                                (new Query\Nested())
                                    ->setPath($key)
                                    ->setQuery(
                                        (new Query\BoolQuery())
                                            ->addMust(['match' => [$key . '.id' => $options[1]]])
                                    )
                            );
                        }
                        $filterQuery->addMust($subQuery);
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
        $text = $value['text'];

        // Check if user does not use advanced syntax
        if (preg_match('/[+|\-"()]/', $text) === 0) {
            if ($value['combination'] == 'phrase') {
                if (preg_match('/[*]/', $text) === 0) {
                    $text = '"' . $text . '"';
                } else {
                    $text = implode(' + ', explode(' ', $text));
                }
            } elseif ($value['combination'] == 'all') {
                $text = implode(' + ', explode(' ', $text));
            }
        }

        return new Query\SimpleQueryString($text, [$field]);
    }

    protected function normalizeString(string $input): string
    {
        $result = $input;

        // Get wildcard charachter position and remove wildcards
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

        $normalizedArray = $this->type->getIndex()->analyze(
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
