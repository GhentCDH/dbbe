<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;

use AppBundle\Model\Manuscript;

class ElasticManuscriptService extends ElasticSearchService
{
    public function __construct(array $config, string $indexPrefix)
    {
        parent::__construct($config, $indexPrefix);
        $this->type = $this->getIndex('manuscripts')->getType('manuscript');
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
        if (!empty($params['filters'])) {
            $params['filters'] = self::classifyFilters($params['filters']);
        }

        $result = $this->search($params);

        $aggregation_result = $this->aggregate(
            self::classifyFilters(['city', 'library', 'collection', 'content', 'patron', 'scribe', 'origin', 'public']),
            !empty($params['filters']) ? $params['filters'] : []
        );

        // Add 'No collection' when necessary
        if (array_key_exists('collection', $aggregation_result)
            || (
                !empty($params['filters'])
                && array_key_exists('object', $params['filters'])
                && array_key_exists('collection', $params['filters']['object'])
                && $params['filters']['object']['collection'] == -1
            )
        ) {
            $aggregation_result['collection'][] = [
                'id' => -1,
                'name' => 'No collection',
            ];
        }

        $result['aggregation'] = $aggregation_result;

        return $result;
    }

    /**
     * Add elasticsearch information to filters
     * @param  array $filters can be a sequential (aggregation) or an associative (query) array
     * @return array
     */
    public static function classifyFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $key => $value) {
            if (isset($value) && $value !== '') {
                // $filters can be a sequential (aggregation) or an associative (query) array
                switch (is_int($key) ? $value : $key) {
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
                        $result['exact_text'][$key] = $value;
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
                    case 'public':
                        if (is_int($key)) {
                            $result['boolean'][] = $value;
                        } else {
                            $result['boolean'][$key] = ($value === 1);
                        }
                        break;
                }
            }
        }
        return $result;
    }
}
