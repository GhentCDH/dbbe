<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;

use AppBundle\Model\Person;

class ElasticPersonService extends ElasticSearchService
{
    public function __construct(array $config, string $indexPrefix, ContainerInterface $container)
    {
        parent::__construct($config, $indexPrefix);
        $this->type = $this->getIndex('persons')->getType('person');
        $this->primaryIdentifierSystemNames = array_map(
            function ($identifier) {
                return $identifier->getSystemName();
            },
            $container->get('identifier_manager')->getIdentifiersByType('person')
        );
    }

    public function setupPersons(): void
    {
        $index = $this->getIndex('persons');
        if ($index->exists()) {
            $index->delete();
        }
        $index->create();

        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        $mapping->setProperties(
            [
                'function' => ['type' => 'nested'],
                'type' => ['type' => 'nested'],
            ]
        );
        $mapping->send();
    }

    public function addPersons(array $persons): void
    {
        $personsElastic = [];
        foreach ($persons as $person) {
            $personsElastic [] = $person->getElastic();
        }

        $this->bulkAdd($personsElastic);
    }

    public function addPerson(Person $person): void
    {
        $this->add($person->getElastic());
    }

    public function delPerson(Person $person): void
    {
        $this->del($person->getId());
    }

    public function searchAndAggregate(array $params): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = $this->classifyFilters($params['filters']);
        }

        $result = $this->search($params);

        // Filter out unnecessary results
        foreach ($result['data'] as $key => $value) {
            unset($result['data'][$key]['historical']);
            unset($result['data'][$key]['type']);
            unset($result['data'][$key]['function']);
            // Keep comments if there was a search, then these will be an array
            if (isset($result['data'][$key]['public_comment']) && is_string($result['data'][$key]['public_comment'])) {
                unset($result['data'][$key]['public_comment']);
            }
            if (isset($result['data'][$key]['private_comment']) && is_string($result['data'][$key]['private_comment'])) {
                unset($result['data'][$key]['private_comment']);
            }
        }

        $aggregationResult = $this->aggregate(
            $this->classifyFilters(array_merge($this->primaryIdentifierSystemNames, ['historical', 'type', 'function', 'public'])),
            !empty($params['filters']) ? $params['filters'] : []
        );

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
                    case 'function':
                    case 'type':
                        if (is_int($key)) {
                            $result['nested'][] = $value;
                        } else {
                            $result['nested'][$key] = $value;
                        }
                        break;
                    case 'name':
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
                    case 'historical':
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
