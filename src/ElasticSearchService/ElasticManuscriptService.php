<?php

namespace App\ElasticSearchService;

use Elastica\Mapping;
use Symfony\Component\DependencyInjection\ContainerInterface;

use App\Model\Role;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\RoleManager;

class ElasticManuscriptService extends ElasticEntityService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        // Add person as content role to the manuscript search page
        $roles = $container->get(RoleManager::class)->getByType('manuscript');
        $roles['person_content'] = Role::getContentRole('person_content');
        parent::__construct(
            $config,
            $indexPrefix,
            'manuscripts',
            $container->get(IdentifierManager::class)->getByType('manuscript'),
            $roles
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
                        'normalizer' => 'text_digits',
                        'ignore_above' => 256,
                    ],
                ],
            ],
            'content' => ['type' => 'nested'],
            'origin' => ['type' => 'nested'],
            'acknowledgement' => ['type' => 'nested'],
            'management' => ['type' => 'nested'],
            'completion_floor' => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
            'completion_ceiling' => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
        ];
        foreach ($this->getRoleSystemNames(true) as $role) {
            $properties[$role] = ['type' => 'nested'];
            $properties[$role . '_public'] = ['type' => 'nested'];
        }
        $this->index->setMapping(new Mapping($properties));
    }

    public function runFullSearch(array $params, bool $viewInternal): array
    {
        $originalFilters = isset($params['filters']) ? $params['filters'] : [];

        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifySearchFilters($params['filters'], $viewInternal);
        }
        $result= $this->search($params);
        return $result;
    }

    public function searchAndAggregate(array $params, bool $viewInternal): array
    {
        $originalFilters = isset($params['filters']) ? $params['filters'] : [];
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifySearchFilters($params['filters'], $viewInternal);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['city']);
            unset($result['data'][$key]['library']);
            unset($result['data'][$key]['collection']);
            unset($result['data'][$key]['shelf']);
            unset($result['data'][$key]['origin']);
            unset($result['data'][$key]['acknowledgement']);
            unset($result['data'][$key]['management']);
            foreach ($this->getRoleSystemNames(true) as $role) {
                unset($result['data'][$key][$role]);
                unset($result['data'][$key][$role . '_public']);
            }

            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }

            if (!$viewInternal) {
                unset($result['data'][$key]['modified']);
            }
        }

        $aggregationFilters = ['city', 'content', 'person', 'origin', 'acknowledgement'];
        if (isset($originalFilters['city'])) {
            $aggregationFilters[] = 'library';
        }
        if (isset($originalFilters['library'])) {
            $aggregationFilters[] = 'collection';
        }
        if (isset($originalFilters['collection'])) {
            $aggregationFilters[] = 'shelf';
        }
        if ($viewInternal) {
            $aggregationFilters[] = 'public';
            $aggregationFilters[] = 'management';
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyAggregationFilters(array_merge($this->getIdentifierSystemNames(), $aggregationFilters), $viewInternal),
            !empty($params['filters']) ? $params['filters'] : []
        );

        // Add 'No collection' when necessary
        // When a library has been selected and no collection has been selected
        if ((!empty($params['filters'])
                && array_key_exists('object', $params['filters'])
                && array_key_exists('library', $params['filters']['object'])
                && !array_key_exists('collection', $params['filters']['object'])
            )
            // When the 'no collection' has been selected
            || (!empty($params['filters'])
                && array_key_exists('object', $params['filters'])
                && array_key_exists('collection', $params['filters']['object'])
                && $params['filters']['object']['collection'] == -1
            )
        ) {
            $result['aggregation']['collection'][] = [
                'id' => -1,
                'name' => 'No collection',
            ];
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
                case 'person':
                    $result['multiple_fields_object_multi'][] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
                    break;
                case 'city':
                case 'library':
                case 'collection':
                    $result['object'][] = $value;
                    break;
                case 'shelf':
                    $result['exact_text'][] = $value;
                    break;
                case 'content':
                case 'origin':
                case 'acknowledgement':
                    $result['nested_multi'][$key] = $value;
                    break;
                case 'management':
                    $result['nested'][] = $value;
                    break;
                case 'public':
                    $result['boolean'][] = $value;
                    break;
            }
        }
        return $result;
    }

    /**
     * Add elasticsearch information to search filters
     * @param  array  $filters
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
                case 'person':
                    if (isset($filters['role'])) {
                        $result['multiple_fields_object_multi'][$key] = [$filters['role'], $value, 'role'];
                    } else {
                        $result['multiple_fields_object_multi'][$key] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
                    }
                    break;
                case 'management':
                    if (isset($filters['management_inverse']) && $filters['management_inverse']) {
                        $result['nested_toggle'][$key] = [$value, false];
                    } else {
                        $result['nested_toggle'][$key] = [$value, true];
                    }
                    break;
                case 'city':
                case 'library':
                case 'collection':
                    $result['object'][$key] = $value;
                    break;
                case 'shelf':
                    $result['exact_text'][$key] = $value;
                    break;
                case 'date':
                    $date_result = [
                        'floorField' => 'date_floor_year',
                        'ceilingField' => 'date_ceiling_year',
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
                case 'content':
                case 'origin':
                case 'acknowledgement':
                    $result['nested_multi'][$key] = $value;
                    break;
                case 'content_op':
                case 'origin_op':
                case 'acknowledgement_op':
                    $result['nested_multi_op'][$key] = $value;
                    break;
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
                    $result['boolean'][$key] = ($value === '1');
                    break;
                case 'exactly_dated':
                    if ($value === true || $value === '1' || $value === 1 || $value === 'true') {
                        $result['date_range'][] = [
                            'floorField' => 'completion_floor',
                            'ceilingField' => 'completion_ceiling',
                            'type' => 'exactly_dated',
                        ];
                    }
                    break;
            }
        }
        return $result;
    }
}
