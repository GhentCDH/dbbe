<?php

namespace App\ElasticSearchService;

use Elastica\Mapping;
use Symfony\Component\DependencyInjection\ContainerInterface;

use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\RoleManager;

class ElasticBibliographyService extends ElasticEntityService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        parent::__construct(
            $config,
            $indexPrefix,
            'bibliographies',
            array_merge(
                $container->get(IdentifierManager::class)->getByType('book'),
                $container->get(IdentifierManager::class)->getByType('bookChapter'),
                $container->get(IdentifierManager::class)->getByType('article')
            ),
            array_merge(
                $container->get(RoleManager::class)->getByType('book'),
                $container->get(RoleManager::class)->getByType('bookChapter'),
                $container->get(RoleManager::class)->getByType('article')
            )
        );
    }

    public function setup(): void
    {
        $index = $this->getIndex();
        if ($index->exists()) {
            $index->delete();
        }
        $index->create(['settings' => Analysis::ANALYSIS]);
        $properties = [
            // Needed for sorting (book: cluster - volume number - volume title)
            'title_sort_key' => [
                'type' => 'text',
                // Needed for sorting
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword',
                        'normalizer' => 'custom_greek',
                        'ignore_above' => 256,
                    ],
                ],
            ],
            'management' => ['type' => 'nested'],
        ];
        foreach ($this->getRoleSystemNames(true) as $role) {
            $properties[$role] = ['type' => 'nested'];
            $properties[$role . '_public'] = ['type' => 'nested'];
        }
        $index->setMapping(new Mapping($properties));
    }

    public function searchAndAggregate(array $params, bool $viewInternal): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifySearchFilters($params['filters'], $viewInternal);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['management']);
            foreach ($this->getRoleSystemNames(true) as $role) {
                if (!$viewInternal || $role !== 'author') {
                    unset($result['data'][$key][$role]);
                }
                if ($role !== 'author') {
                    unset($result['data'][$key][$role . '_public']);
                }
            }

            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }
        }

        $aggregationFilters = ['type', 'person'];
        if ($viewInternal) {
            $aggregationFilters[] = 'public';
            $aggregationFilters[] = 'management';
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyAggregationFilters(array_merge($this->getIdentifierSystemNames(), $aggregationFilters), $viewInternal),
            !empty($params['filters']) ? $params['filters'] : []
        );

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
                $result['multiple_fields_object_multi'][] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
                break;
            case 'management':
                $result['nested'][] = $value;
                break;
            case 'type':
                $result['object'][] = $value;
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
                    $result['multiple_fields_object_multi'][$key] = [[$filters['role']], $value, 'role'];
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
            case 'type':
                $result['object'][$key] = $value;
                break;
            case 'title':
                $result['text'][$key] = [
                    'text' => $value,
                    'combination' => $filters['title_type'],
                ];
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
