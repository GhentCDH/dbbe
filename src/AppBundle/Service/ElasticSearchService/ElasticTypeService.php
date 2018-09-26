<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElasticTypeService extends ElasticBaseService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        parent::__construct(
            $config,
            $indexPrefix,
            'types',
            'type',
            $container->get('identifier_manager')->getIdentifiersByType('type'),
            $container->get('role_manager')->getRolesByType('type')
        );
    }

    public function setup(): void
    {
        $index = $this->getIndex();
        if ($index->exists()) {
            $index->delete();
        }
        // Configure analysis
        $index->create(GreekAnalysis::ANALYSIS);

        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        $properties = [
            'text' => [
                'type' => 'text',
                'analyzer' => 'custom_greek',
            ],
            'meter' => ['type' => 'nested'],
            'subject' => ['type' => 'nested'],
            'keyword' => ['type' => 'nested'],
            'genre' => ['type' => 'nested'],
        ];
        foreach ($this->getRoleSystemNames(true) as $role) {
            $properties[$role] = ['type' => 'nested'];
            $properties[$role . '_public'] = ['type' => 'nested'];
        }
        $mapping->setProperties($properties);
        $mapping->send();
    }

    public function searchAndAggregate(array $params, bool $viewInternal): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifyFilters($params['filters'], $viewInternal);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['genre']);
            unset($result['data'][$key]['meter']);
            unset($result['data'][$key]['subject']);
            unset($result['data'][$key]['text_status']);
            foreach ($this->getRoleSystemNames(true) as $role) {
                unset($result['data'][$key][$role]);
                unset($result['data'][$key][$role . '_public']);
            }

            // Keep text / title if there was a search, then these will be an array
            if (isset($result['data'][$key]['text']) && is_string($result['data'][$key]['text'])) {
                unset($result['data'][$key]['text']);
            }
            if (isset($result['data'][$key]['title']) && is_string($result['data'][$key]['title'])) {
                unset($result['data'][$key]['title']);
            }

            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }

            // Number of (public) occurrences
            if ($viewInternal) {
                unset($result['data'][$key]['number_of_occurrences_public']);
            } else {
                $result['data'][$key]['number_of_occurrences'] = $result['data'][$key]['number_of_occurrences_public'];
                unset($result['data'][$key]['number_of_occurrences_public']);
            }
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyFilters(
                array_merge(
                    $this->getIdentifierSystemNames(),
                    ['meter', 'subject', 'keyword', 'person', 'genre', 'public', 'text_status']
                ),
                $viewInternal
            ),
            !empty($params['filters']) ? $params['filters'] : []
        );

        // Add 'No genre' when necessary
        if (array_key_exists('genre', $result['aggregation'])
            || (
                !empty($params['filters'])
                && array_key_exists('object', $params['filters'])
                && array_key_exists('genre', $params['filters']['object'])
                && $params['filters']['object']['genre'] == -1
            )
        ) {
            $result['aggregation']['genre'][] = [
                'id' => -1,
                'name' => 'No genre',
            ];
        }

        // Remove non public fields if no access rights
        // Add 'no-selectors' for primary identifiers
        if (!$viewInternal) {
            unset($result['aggregation']['public']);
            foreach ($result['data'] as $key => $value) {
                unset($result['data'][$key]['public']);
            }
        } else {
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
     * @param  bool $viewInternal indicates whether internal (non-public) data can be displayed
     * @return array
     */
    public function classifyFilters(array $filters, bool $viewInternal): array
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
                    // Person roles
                    case 'person':
                        if (is_int($key)) {
                            $result['multiple_fields_object'][] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
                        } else {
                            if (isset($filters['role'])) {
                                $result['multiple_fields_object'][$key] = [[$filters['role']], $value, 'role'];
                            } else {
                                $result['multiple_fields_object'][$key] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
                            }
                        }
                        break;
                    case 'text':
                        $result['multiple_text'][$key] = [
                            'text' => [
                                'text' => $value,
                                'type' => $filters['text_type'],
                            ],
                            'title' => [
                                'text' => $value,
                                'type' => $filters['text_type'],
                            ],
                        ];
                        break;
                    case 'text_status':
                        if (is_int($key)) {
                            $result['object'][] = $value;
                        } else {
                            $result['object'][$key] = $value;
                        }
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
                    case 'meter':
                    case 'subject':
                    case 'keyword':
                    case 'genre':
                        if (is_int($key)) {
                            $result['nested'][] = $value;
                        } else {
                            $result['nested'][$key] = $value;
                        }
                        break;
                    case 'public_comment':
                        $result['text'][$key] = [
                            'text' => $value,
                            'type' => 'any',
                        ];
                        break;
                    case 'comment':
                        $result['multiple_text'][$key] = [
                            'public_comment'=> [
                                'text' => $value,
                                'type' => 'any',
                            ],
                            'private_comment'=> [
                                'text' => $value,
                                'type' => 'any',
                            ],
                        ];
                        break;
                    case 'public':
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
