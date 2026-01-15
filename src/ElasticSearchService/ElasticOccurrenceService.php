<?php

namespace App\ElasticSearchService;

use Elastica\Mapping;
use Symfony\Component\DependencyInjection\ContainerInterface;

use App\Model\Role;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\RoleManager;

class ElasticOccurrenceService extends ElasticEntityService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        // Add person as subject role to the occurrence search page
        $roles = $container->get(RoleManager::class)->getByType('occurrence');
        $roles['person_subject'] = Role::getSubjectRole('person_subject');
        parent::__construct(
            $config,
            $indexPrefix,
            'occurrences',
            $container->get(IdentifierManager::class)->getByType('occurrence'),
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
            'incipit' => [
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
            'title_original' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_original',
            ],
            'title_stemmer' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_stemmer',
            ],
            'text_original' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_original',
            ],
            'text_stemmer' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_stemmer',
            ],
            'metre' => ['type' => 'nested'],
            'subject' => ['type' => 'nested'],
            'manuscript_content' => ['type' => 'nested'],
            'manuscript_content_public' => ['type' => 'nested'],
            'genre' => ['type' => 'nested'],
            'acknowledgement' => ['type' => 'nested'],
            'management' => ['type' => 'nested'],
        ];
        foreach ($this->getRoleSystemNames(true) as $role) {
            $properties[$role] = ['type' => 'nested'];
            $properties[$role . '_public'] = ['type' => 'nested'];
        }
        $this->index->setMapping(new Mapping($properties));
    }

    public function runFullSearch(array $params, bool $viewInternal): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifySearchFilters($params['filters'], $viewInternal);
        }
        return $this->search($params);
    }

    public function searchAndAggregate(array $params, bool $viewInternal): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifySearchFilters($params['filters'], $viewInternal);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['prevId']);
            unset($result['data'][$key]['manuscript_content']);
            unset($result['data'][$key]['manuscript_content_public']);
            unset($result['data'][$key]['genre']);
            unset($result['data'][$key]['subject']);
            unset($result['data'][$key]['dbbe']);
            unset($result['data'][$key]['text_status']);
            unset($result['data'][$key]['acknowledgement']);
            unset($result['data'][$key]['management']);
            foreach ($this->getRoleSystemNames(true) as $role) {
                unset($result['data'][$key][$role]);
                unset($result['data'][$key][$role . '_public']);
            }

            // Keep text / title if there was a search, then these will be an array
            foreach (['text', 'title'] as $field) {
                unset($result['data'][$key][$field . '_stemmer']);
                unset($result['data'][$key][$field . '_original']);
                if (isset($result['data'][$key][$field]) && is_string($result['data'][$key][$field])) {
                    unset($result['data'][$key][$field]);
                }
            }

            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }
            if (isset($result['data'][$key]['palaeographical_info']) && is_string($result['data'][$key]['palaeographical_info'])) {
                unset($result['data'][$key]['palaeographical_info']);
            }
            if (isset($result['data'][$key]['contextual_info']) && is_string($result['data'][$key]['contextual_info'])) {
                unset($result['data'][$key]['contextual_info']);
            }

            if (!$viewInternal) {
                unset($result['data'][$key]['modified']);
            }
        }

        $aggregationFilters = ['metre', 'subject', 'manuscript_content', 'person', 'genre', 'dbbe', 'text_status', 'acknowledgement', 'id', 'prev_id'];
        if ($viewInternal) {
            $aggregationFilters[] = 'public';
            $aggregationFilters[] = 'management';
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyAggregationFilters(array_merge($this->getIdentifierSystemNames(), $aggregationFilters), $viewInternal),
            !empty($params['filters']) ? $params['filters'] : []
        );

        if (!$viewInternal && isset($result['aggregation']['manuscript_content_public'])) {
            $result['aggregation']['manuscript_content'] = $result['aggregation']['manuscript_content_public'];
            unset($result['aggregation']['manuscript_content_public']);
        }

        // Add 'No genre' when necessary
        if (array_key_exists('genre', $result['aggregation'])
            || (
                !empty($params['filters'])
                && array_key_exists('nested', $params['filters'])
                && array_key_exists('genre', $params['filters']['nested'])
                && $params['filters']['nested']['genre'] == -1
            )
        ) {
            $result['aggregation']['genre'][] = [
                'id' => -1,
                'name' => 'No genre',
            ];
        }

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
                $result['exact_text'][] = $value;
                continue;
            }

            switch ($value) {
            case 'id':
            case 'prev_id':
                $result['numeric'][] = $value;
                break;
            case 'person':
                $result['multiple_fields_object_multi'][] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
                break;
            case 'metre':
            case 'genre':
            case 'subject':
            case 'acknowledgement':
                $result['nested_multi'][] = $value;
                break;
            case 'manuscript_content':
                $result['nested_multi'][] = $viewInternal ? $value : $value . '_public';
                break;
            case 'management':
                $result['nested'][] = $value;
                break;
            case 'text_status':
                $result['object'][] = $value;
                break;
            case 'public':
            case 'dbbe':
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
            case 'id':
            case 'prev_id':
                $result['numeric'][$key] = $value;
                break;
            case 'text':
                switch ($filters['text_fields']) {
                    case 'text':
                    case 'title':
                        $result['text'][$key] = [
                            'field' => $filters['text_fields'] . '_' . $filters['text_stem'],
                            'text' => $value,
                            'combination' => $filters['text_combination'],
                        ];
                        break;
                    case 'all':
                        $result['multiple_text'][$key] = [
                            'text' => [
                                'field' => 'text_' . $filters['text_stem'],
                                'text' => $value,
                                'combination' => $filters['text_combination'],
                            ],
                            'title' => [
                                'field' => 'title_' . $filters['text_stem'],
                                'text' => $value,
                                'combination' => $filters['text_combination'],
                            ],
                        ];
                        break;
                }
                break;
            case 'metre':
            case 'genre':
            case 'subject':
            case 'acknowledgement':
                $result['nested_multi'][$key] = $value;
                break;
            case 'manuscript_content':
                if ($viewInternal) {
                    $result['nested_multi'][$key] = $value;
                } else {
                    $result['nested_multi'][$key . '_public'] = $value;
                }
                break;
            case 'metre_op':
            case 'genre_op':
            case 'subject_op':
            case 'acknowledgement_op':
                $result['nested_multi_op'][$key] = $value;
                break;
            case 'manuscript_content_op':
                if ($viewInternal) {
                    $result['nested_multi_op'][$key] = $value;
                } else {
                    $result['nested_multi_op']['manuscript_content_public_op'] = $value;
                }
                break;
            case 'text_status':
                $result['object'][$key] = $value;
                break;
            case 'person':
                if (isset($filters['role'])) {
                    $result['multiple_fields_object_multi'][$key] = [$filters['role'], $value, 'role'];
                } else {
                    $result['multiple_fields_object_multi'][$key] = [$this->getRoleSystemNames($viewInternal), $value, 'role'];
                }
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
            case 'management':
                if (isset($filters['management_inverse']) && $filters['management_inverse']) {
                    $result['nested_toggle'][$key] = [$value, false];
                } else {
                    $result['nested_toggle'][$key] = [$value, true];
                }
                break;
            case 'public_comment':
                $result['multiple_text'][$key] = [
                    'public_comment'=> [
                        'text' => $value,
                        'combination' => 'any',
                    ],
                    'palaeographical_info'=> [
                        'text' => $value,
                        'combination' => 'any',
                    ],
                    'contextual_info'=> [
                        'text' => $value,
                        'combination' => 'any',
                    ],
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
                    'palaeographical_info'=> [
                        'text' => $value,
                        'combination' => 'any',
                    ],
                    'contextual_info'=> [
                        'text' => $value,
                        'combination' => 'any',
                    ],
                ];
                break;
            case 'public':
            case 'dbbe':
                $result['boolean'][$key] = ($value === '1');
                break;
            }
        }
        return $result;
    }
}
