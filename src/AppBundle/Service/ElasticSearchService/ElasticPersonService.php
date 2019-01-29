<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElasticPersonService extends ElasticBaseService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        parent::__construct(
            $config,
            $indexPrefix,
            'persons',
            'person',
            $container->get('identifier_manager')->getByType('person')
        );
    }

    public function setup(): void
    {
        $index = $this->getIndex();
        if ($index->exists()) {
            $index->delete();
        }
        // Configure analysis
        $index->create(CaseInsensitiveAnalysis::ANALYSIS);

        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        $mapping->setProperties(
            [
                'name' => [
                    'type' => 'text',
                    // Needed for sorting
                    'fields' => [
                        'keyword' => [
                            'type' => 'keyword',
                            'normalizer' => 'case_insensitive',
                            'ignore_above' => 256,
                        ],
                    ],
                ],
                'role' => ['type' => 'nested'],
                'office' => ['type' => 'nested'],
                'management' => ['type' => 'nested'],
            ]
        );
        $mapping->send();
    }

    public function searchAndAggregate(array $params, bool $viewInternal): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifyFilters($params['filters']);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['historical']);
            unset($result['data'][$key]['modern']);
            unset($result['data'][$key]['role']);
            unset($result['data'][$key]['management']);
            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }
        }

        $aggregationFilters = ['historical', 'modern', 'role', 'office', 'self_designation', 'origin'];
        if ($viewInternal) {
            $aggregationFilters[] = 'public';
            $aggregationFilters[] = 'management';
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyFilters(array_merge($this->getIdentifierSystemNames(), $aggregationFilters), $viewInternal),
            !empty($params['filters']) ? $params['filters'] : []
        );

        // Add 'no-selectors' for primary identifiers
        if ($viewInternal) {
            foreach ($this->primaryIdentifiers as $identifier) {
                $result['aggregation'][$identifier->getSystemName()][] = [
                    'id' => -1,
                    'name' => 'No ' . $identifier->getName(),
                ];
            }
        }

        return $result;
    }

    /**
     * Add elasticsearch information to filters
     * @param  array $filters can be a sequential (aggregation) or an associative (query) array
     * @return array
     */
    public function classifyFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $key => $value) {
            if (isset($value) && $value !== '') {
                // $filters can be a sequential (aggregation) or an associative (query) array
                $switch = is_int($key) ? $value : $key;
                switch ($switch) {
                    // Primary identifiers
                    case in_array($switch, $this->getIdentifierSystemNames()) ? $switch : null:
                        if (is_int($key)) {
                            $result['exact_text'][] = $value;
                        } else {
                            $result['exact_text'][$key] = $value;
                        }
                        break;
                    // Management collections
                    case 'management':
                        if (is_int($key)) {
                            $result['nested'][] = $value;
                        } else {
                            if (isset($filters['management_inverse']) && $filters['management_inverse']) {
                                $result['nested_toggle'][$key] = [$value, false];
                            } else {
                                $result['nested_toggle'][$key] = [$value, true];
                            }
                        }
                        break;
                    case 'date':
                        $date_result = [
                            'floorField' => 'born_date_floor_year',
                            'ceilingField' => 'death_date_ceiling_year',
                        ];
                        if (array_key_exists('from', $value)) {
                            $date_result['startDate'] = $value['from'];
                        }
                        if (array_key_exists('to', $value)) {
                            $date_result['endDate'] = $value['to'];
                        }
                        $result['date_range'][] = $date_result;
                        break;
                    case 'role':
                    case 'office':
                        if (is_int($key)) {
                            $result['nested'][] = $value;
                        } else {
                            $result['nested'][$key] = $value;
                        }
                        break;
                    case 'self_designation':
                        if (is_int($key)) {
                            $result['exact_text'][] = $value;
                        } else {
                            $result['exact_text'][$key] = $value;
                        }
                        break;
                    case 'origin':
                        if (is_int($key)) {
                            $result['object'][] = $value;
                        } else {
                            $result['object'][$key] = $value;
                        }
                        break;
                    case 'name':
                    case 'public_comment':
                        $result['text'][$key] = [
                            'text' => $value,
                            'combination' => 'any',
                        ];
                        break;
                    case 'comment':
                        $result['multiple_text'][$key] = [
                            'public_comment'=> [
                                'text' => $value,
                                'combination' => 'any',
                            ],
                            'private_comment'=> [
                                'text' => $value,
                                'combination' => 'any',
                            ],
                        ];
                        break;
                    case 'public':
                    case 'historical':
                    case 'modern':
                        if (is_int($key)) {
                            $result['boolean'][] = $value;
                        } else {
                            $result['boolean'][$key] = ($value === '1');
                        }
                        break;
                }
            }
        }
        return $result;
    }
}
