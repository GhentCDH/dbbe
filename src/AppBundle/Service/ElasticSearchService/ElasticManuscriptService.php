<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;

use AppBundle\Model\Manuscript;

class ElasticManuscriptService extends ElasticEntityService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        parent::__construct(
            $config,
            $indexPrefix,
            'manuscripts',
            'manuscript',
            $container->get('identifier_manager')->getIdentifiersByType('manuscript'),
            $container->get('role_manager')->getRolesByType('manuscript')
        );
    }

    public function setupManuscripts(): void
    {
        $index = $this->getIndex('manuscripts');
        if ($index->exists()) {
            $index->delete();
        }
        $index->create();

        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        $mapping->setProperties(
            [
                'content' => ['type' => 'nested'],
                'patron' => ['type' => 'nested'],
                'scribe' => ['type' => 'nested'],
                'related' => ['type' => 'nested'],
                'patron_public' => ['type' => 'nested'],
                'scribe_public' => ['type' => 'nested'],
                'related_public' => ['type' => 'nested'],
                'origin' => ['type' => 'nested'],
            ]
        );
        $mapping->send();
    }

    public function searchAndAggregate(array $params, bool $viewInternal): array
    {
        $aggregationFilters = ['city', 'content', 'person', 'origin', 'public'];
        if (!empty($params['filters']) && isset($params['filters']['city'])) {
            $aggregationFilters[] = 'library';
        }
        if (!empty($params['filters']) && isset($params['filters']['library'])) {
            $aggregationFilters[] = 'collection';
        }
        if (!empty($params['filters']) && isset($params['filters']['collection'])) {
            $aggregationFilters[] = 'shelf';
        }

        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifyFilters($params['filters'], $viewInternal);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['city']);
            unset($result['data'][$key]['library']);
            unset($result['data'][$key]['collection']);
            unset($result['data'][$key]['shelf']);
            unset($result['data'][$key]['patron']);
            unset($result['data'][$key]['scribe']);
            unset($result['data'][$key]['origin']);

            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }
        }

        $result['aggregation'] = $this->aggregate(
            $this->classifyFilters(array_merge($this->getIdentifierSystemNames(), $aggregationFilters), $viewInternal),
            !empty($params['filters']) ? $params['filters'] : []
        );

        // Filter out unnecessary results
        // Add 'No collection' when necessary
        if (array_key_exists('collection', $result['aggregation'])
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
                    case 'city':
                    case 'library':
                    case 'collection':
                        if (is_int($key)) {
                            $result['object'][] = $value;
                        } else {
                            $result['object'][$key] = $value;
                        }
                        break;
                    case 'shelf':
                        if (is_int($key)) {
                            $result['exact_text'][] = $value;
                        } else {
                            $result['exact_text'][$key] = $value;
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
                    case 'content':
                    case 'origin':
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
