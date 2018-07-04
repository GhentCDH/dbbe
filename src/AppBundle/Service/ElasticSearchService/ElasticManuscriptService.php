<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;

use AppBundle\Model\Manuscript;

class ElasticManuscriptService extends ElasticSearchService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        parent::__construct($config, $indexPrefix);
        $this->type = $this->getIndex('manuscripts')->getType('manuscript');
        $this->primaryIdentifierSystemNames = array_map(
            function ($identifier) {
                return $identifier->getSystemName();
            },
            $container->get('identifier_manager')->getIdentifiersByType('manuscript')
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
                'origin' => ['type' => 'nested'],
            ]
        );
        $mapping->send();
    }

    public function addManuscripts(array $manuscripts): void
    {
        $manuscriptsElastic = [];
        foreach ($manuscripts as $manuscript) {
            $manuscriptsElastic [] = $manuscript->getElastic();
        }

        $this->bulkAdd($manuscriptsElastic);
    }

    public function addManuscript(Manuscript $manuscript): void
    {
        $this->add($manuscript->getElastic());
    }

    public function delManuscript(Manuscript $manuscript): void
    {
        $this->del($manuscript->getId());
    }

    public function searchAndAggregate(array $params): array
    {
        $aggregationFilters = ['city', 'content', 'patron', 'scribe', 'origin', 'public'];
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
            $params['filters'] = $this->classifyFilters($params['filters']);
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

        $aggregationResult = $this->aggregate(
            $this->classifyFilters(array_merge($this->primaryIdentifierSystemNames, $aggregationFilters)),
            !empty($params['filters']) ? $params['filters'] : []
        );
        // Filter out unnecessary results

        // Add 'No collection' when necessary
        if (array_key_exists('collection', $aggregationResult)
            || (
                !empty($params['filters'])
                && array_key_exists('object', $params['filters'])
                && array_key_exists('collection', $params['filters']['object'])
                && $params['filters']['object']['collection'] == -1
            )
        ) {
            $aggregationResult['collection'][] = [
                'id' => -1,
                'name' => 'No collection',
            ];
        }

        $result['aggregation'] = $aggregationResult;

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
                    case in_array($switch, $this->primaryIdentifierSystemNames) ? $switch : null:
                        if (is_int($key)) {
                            $result['exact_text'][] = $value;
                        } else {
                            $result['exact_text'][$key] = $value;
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
                    case 'patron':
                    case 'scribe':
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
