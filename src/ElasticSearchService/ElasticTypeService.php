<?php

namespace App\ElasticSearchService;

use Elastica\Mapping;
use Symfony\Component\DependencyInjection\ContainerInterface;

use App\Model\Role;
use App\ObjectStorage\IdentifierManager;
use App\ObjectStorage\RoleManager;

class ElasticTypeService extends ElasticEntityService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        // Add person as subject role to the type search page
        $roles = $container->get(RoleManager::class)->getByType('type');
        $roles['person_subject'] = Role::getSubjectRole('person_subject');
        parent::__construct(
            $config,
            $indexPrefix,
            'types',
            $container->get(IdentifierManager::class)->getByType('type'),
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
            'title_GR_original' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_original',
            ],
            'text_original' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_original',
            ],
            'text_stemmer' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_stemmer',
            ],
            'lemma' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_original',
            ],
            'metre' => ['type' => 'nested'],
            'subject' => ['type' => 'nested'],
            'tag' => ['type' => 'nested'],
            'genre' => ['type' => 'nested'],
            'translation_language' => ['type' => 'nested'],
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
        // Add additional results
        foreach ($result['data'] as $key => $value) {
            // Add text_lemma for lemma queries
            // The highlighting of lemmas need to be transferred to the original_text
            if (isset($result['data'][$key]['lemma']) && is_array($result['data'][$key]['lemma'])) {
                $result['data'][$key]['lemma_text'] = [];
                foreach ($result['data'][$key]['lemma'] as $row => $highlight) {
                    $original_line = explode("\n", $result['data'][$key]['text_original'])[$row];
                    $original_words = explode(' ', $original_line);
                    $lemma_text_words = [];
                    foreach (explode(' ', $highlight) as $word_index => $word) {
                        if (substr($word, 0, 6) === '<mark>') {
                            $lemma_text_words[] = '<mark>' . $original_words[$word_index] . '</mark>';
                        } else {
                            $lemma_text_words[] = $original_words[$word_index];
                        }
                    }
                    $result['data'][$key]['lemma_text'][$row] = implode(' ', $lemma_text_words);
                }
                # Unset lemma result that is no longer of use
                unset($result['data'][$key]['lemma']);
            }

            unset($result['data'][$key]['prevId']);
            unset($result['data'][$key]['tag']);
            unset($result['data'][$key]['translated']);
            unset($result['data'][$key]['translation_language']);
            unset($result['data'][$key]['dbbe']);
            unset($result['data'][$key]['text_status']);
            unset($result['data'][$key]['critical_status']);
            unset($result['data'][$key]['acknowledgement']);
            unset($result['data'][$key]['management']);
            foreach ($this->getRoleSystemNames(true) as $role) {
                unset($result['data'][$key][$role]);
                unset($result['data'][$key][$role . '_public']);
            }

            foreach (['text', 'title_GR', 'title_LA'] as $field) {
                unset($result['data'][$key][$field . '_stemmer']);
                unset($result['data'][$key][$field . '_original']);
            }
            // Keep text / title / lemma if there was a search, then these will be an array
            foreach (['text', 'title', 'lemma'] as $field) {
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

            // Number of (public) occurrences
            if ($viewInternal) {
                unset($result['data'][$key]['number_of_occurrences_public']);
            } else {
                if (array_key_exists('number_of_occurrences_public', $result['data'][$key])) {
                    $result['data'][$key]['number_of_occurrences'] = $result['data'][$key]['number_of_occurrences_public'];
                } else {
                    $result['data'][$key]['number_of_occurrences'] = 0;
                }
                unset($result['data'][$key]['number_of_occurrences_public']);
            }

            if (!$viewInternal) {
                unset($result['data'][$key]['created']);
                unset($result['data'][$key]['modified']);
            }
        }

        $aggregationFilters = ['metre', 'subject', 'tag', 'translated', 'translation_language', 'person', 'genre', 'dbbe', 'text_status', 'critical_status', 'acknowledgement', 'id', 'prev_id'];
        if ($viewInternal) {
            $aggregationFilters[] = 'public';
            $aggregationFilters[] = 'management';
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyAggregationFilters(array_merge($this->getIdentifierSystemNames(), $aggregationFilters), $viewInternal),
            !empty($params['filters']) ? $params['filters'] : []
        );

        // Add 'No genre' when necessary
        if (
            array_key_exists('genre', $result['aggregation'])
            || (!empty($params['filters'])
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
                case 'id':
                case 'prev_id':
                    $result['numeric'][] = $value;
                    break;
                case 'person':
                    // Display key without _public in aggregation, add the _public
                    $result['multiple_fields_object_multi'][] = [array_values($this->getRoleSystemNames(TRUE)), $value, 'role'];
                    break;
                case 'metre':
                case 'subject':
                case 'tag':
                case 'genre':
                case 'translation_language':
                case 'acknowledgement':
                    $result['nested_multi'][$key] = $value;
                    break;
                case 'management':
                    $result['nested'][] = $value;
                    break;
                case 'text_status':
                case 'critical_status':
                    $result['object'][] = $value;
                    break;
                case 'public':
                case 'translated':
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
            if (str_ends_with($key, '_available') && in_array(substr($key, 0, -10), $this->getIdentifierSystemNames())) {
                $result['boolean'][$key] = ($value === '1');
                continue;
            }
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
                            $result['text'][$key] = [
                                'field' => $filters['text_fields'] . '_' . $filters['text_stem'],
                                'text' => $value,
                                'combination' => $filters['text_combination'],
                            ];
                            break;
                        case 'title':
                            $result['multiple_text'][$key] = [
                                'title_GR' => [
                                    'field' => 'title_GR_' . $filters['text_stem'],
                                    'text' => $value,
                                    'combination' => $filters['text_combination'],
                                ],
                                'title_LA' => [
                                    'field' => 'title_LA_' . $filters['text_stem'],
                                    'text' => $value,
                                    'combination' => $filters['text_combination'],
                                ],
                            ];
                            break;
                        case 'all':
                            $result['multiple_text'][$key] = [
                                'text' => [
                                    'field' => 'text_' . $filters['text_stem'],
                                    'text' => $value,
                                    'combination' => $filters['text_combination'],
                                ],
                                'title_GR' => [
                                    'field' => 'title_GR_' . $filters['text_stem'],
                                    'text' => $value,
                                    'combination' => $filters['text_combination'],
                                ],
                                'title_LA' => [
                                    'field' => 'title_LA_' . $filters['text_stem'],
                                    'text' => $value,
                                    'combination' => $filters['text_combination'],
                                ],
                            ];
                            break;
                    }
                    break;
                case 'lemma':
                    $result['text'][$key] = [
                        'text' => $value,
                        'combination' => 'any',
                    ];
                    break;
                case 'metre':
                case 'subject':
                case 'tag':
                case 'genre':
                case 'translation_language':
                case 'acknowledgement':
                    $result['nested_multi'][$key] = $value;
                    break;
                case 'metre_op':
                case 'subject_op':
                case 'tag_op':
                case 'genre_op':
                case 'translation_language_op':
                case 'acknowledgement_op':
                    $result['nested_multi_op'][$key] = $value;
                    break;
                case 'text_status':
                case 'critical_status':
                    $result['object'][$key] = $value;
                    break;
                case 'person':
                    if (isset($filters['role'])) {
                        $result['multiple_fields_object_multi'][$key] = [
                            array_map(
                                function ($roleName) use ($viewInternal) {
                                    return $viewInternal ? $roleName : $roleName . '_public';
                                },
                                $filters['role']
                            ),
                            $value,
                            'role'
                        ];
                    } else {
                        $result['multiple_fields_object_multi'][$key] = [array_values($this->getRoleSystemNames($viewInternal)), $value, 'role'];
                    }
                    break;
                case 'management':
                    if (isset($filters['management_inverse']) && $filters['management_inverse']) {
                        $result['nested_toggle'][$key] = [$value, false];
                    } else {
                        $result['nested_toggle'][$key] = [$value, true];
                    }
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
                case 'translated':
                case 'dbbe':
                    $result['boolean'][$key] = ($value === '1');
                    break;
            }
        }
        return $result;
    }
}
