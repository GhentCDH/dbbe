<?php

namespace App\ElasticSearchService;

use Elastica\Mapping;
use Symfony\Component\DependencyInjection\ContainerInterface;

use App\ObjectStorage\IdentifierManager;

class ElasticPersonService extends ElasticEntityService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        parent::__construct(
            $config,
            $indexPrefix,
            'persons',
            $container->get(IdentifierManager::class)->getByType('person')
        );
    }

    public function setup(): void
    {
        if ($this->index->exists()) {
            $this->index->delete();
        }
        // Configure analysis
        $this->index->create(['settings' => Analysis::ANALYSIS]);

        $properties = [
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
            'self_designation' => ['type' => 'nested'],
            'office' => ['type' => 'nested'],
            'management' => ['type' => 'nested'],
            'acknowledgement' => ['type' => 'nested'],
            'origin' => ['type' => 'nested'],
        ];
        $this->index->setMapping(new Mapping($properties));
    }

    public function runFullSearch(array $params, bool $viewInternal): array {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifySearchFilters($params['filters'], $viewInternal);
        }

        $result= $this->search($params);
        return $result;
    }

    public function searchAndAggregate(array $params, bool $viewInternal): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifySearchFilters($params['filters'], $viewInternal);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['historical']);
            unset($result['data'][$key]['modern']);
            unset($result['data'][$key]['role']);
            unset($result['data'][$key]['acknowledgement']);
            unset($result['data'][$key]['management']);
            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }

            if (!$viewInternal) {
                unset($result['data'][$key]['created']);
                unset($result['data'][$key]['modified']);
            }
        }

        $aggregationFilters = ['historical', 'modern', 'role', 'office', 'self_designation', 'origin', 'acknowledgement'];
        if ($viewInternal) {
            $aggregationFilters[] = 'public';
            $aggregationFilters[] = 'management';
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyAggregationFilters(array_merge($this->getIdentifierSystemNames(), $aggregationFilters), $viewInternal),
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
     * Add elasticsearch information to aggregation filters
     * @param  array  $filters
     * @param  bool   $viewInternal indicates whether internal (non-public) data can be displayed
     * @return array
     */
    public function classifyAggregationFilters(array $filters, bool $viewInternal): array
    {
        $result = [];
        foreach ($filters as $key => $value) {
            // Primary identifiers
            if (in_array($value, $this->getIdentifierSystemNames())) {
                $result['boolean'][] = $value . '_available';
                $result['exact_text'][] = $value;
                continue;
            }

            switch ($value) {
                case 'role':
                case 'self_designation':
                case 'office':
                case 'origin':
                    $result['nested_multi'][$key] = $value;
                    break;
                case 'management':
                    $result['nested'][] = $value;
                    break;
                case 'public':
                case 'historical':
                case 'modern':
                    $result['boolean'][] = $value;
                    break;
                case 'acknowledgement':
                    $result['nested_multi'][] = $value;
                    break;
            }
        }
        return $result;
    }

    /**
     * Add elasticsearch information to search filters
     * @param  array $filters
     * @param  bool   $viewInternal indicates whether internal (non-public) data can be displayed
     * @return array
     */
    public function classifySearchFilters(array $filters, bool $viewInternal): array
    {
        $result = [];
        foreach ($filters as $key => $value) {
            if (!isset($value) || $value === '') {
                continue;
            }

            // Primary identifiers
            if (str_ends_with($key, '_available') && in_array(substr($key, 0, -10), $this->getIdentifierSystemNames())) {
                $result['boolean'][$key] = ($value === '1');
                continue;
            }
            if (in_array($key, $this->getIdentifierSystemNames())) {
                $result['exact_text'][$key] = $value;
                continue;
            }

            switch ($key) {
                case 'date':
                    $date_result = [
                        'floorField' => 'born_date_floor_year',
                        'ceilingField' => 'death_date_ceiling_year',
                        'type' => $filters['date_search_type'],
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
                case 'self_designation':
                case 'office':
                case 'acknowledgement':
                    $result['nested_multi'][$key] = $value;
                    break;
                case 'acknowledgement_op':
                    $result['nested_multi_op'][$key] = $value;
                    break;
                case 'origin':
                    $result['nested_multi'][$key] = $value;
                    break;
                case 'role_op':
                case 'self_designation_op':
                case 'office_op':
                case 'origin_op':
                    $result['nested_multi_op'][$key] = $value;
                    break;
                case 'management':
                    if (isset($filters['management_inverse']) && $filters['management_inverse']) {
                        $result['nested_toggle'][$key] = [$value, false];
                    } else {
                        $result['nested_toggle'][$key] = [$value, true];
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
                        'public_comment' => [
                            'text' => $value,
                            'combination' => 'any',
                        ],
                        'private_comment' => [
                            'text' => $value,
                            'combination' => 'any',
                        ],
                    ];
                    break;
                case 'public':
                case 'historical':
                case 'modern':
                    $result['boolean'][$key] = ($value === '1');
                    break;
            }
        }
        return $result;
    }
}
