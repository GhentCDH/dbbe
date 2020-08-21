<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;

use AppBundle\Model\Role;

class ElasticManuscriptService extends ElasticEntityService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        // Add person as content role to the manuscript search page
        $roles = $container->get('role_manager')->getByType('manuscript');
        $roles['person_content'] = Role::getContentRole('person_content');
        parent::__construct(
            $config,
            $indexPrefix,
            'manuscripts',
            'manuscript',
            $container->get('identifier_manager')->getByType('manuscript'),
            $roles
        );
    }

    public function setup(): void
    {
        $index = $this->getIndex();
        if ($index->exists()) {
            $index->delete();
        }
        // Configure analysis
        $index->create(Analysis::ANALYSIS);

        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
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
                unset($result['data'][$key]['created']);
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
            || (
                !empty($params['filters'])
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

        // Add 'no-selectors' for primary identifiers if access rights
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
                $result['exact_text'][] = $value;
                continue;
            }

            switch ($value) {
            case 'person':
                $result['multiple_fields_object'][] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
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
            if (in_array($key, $this->getIdentifierSystemNames())) {
                $result['exact_text'][$key] = $value;
                continue;
            }

            switch ($key) {
            case 'person':
                if (isset($filters['role'])) {
                    $result['multiple_fields_object'][$key] = [[$filters['role']], $value, 'role'];
                } else {
                    $result['multiple_fields_object'][$key] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
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
                $result['nested'][$key] = $value;
                break;
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
                $result['boolean'][$key] = ($value === '1');
                break;
            }
        }
        return $result;
    }
}
