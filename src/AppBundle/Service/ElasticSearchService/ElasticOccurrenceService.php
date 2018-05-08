<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;

use AppBundle\Model\Occurrence;

class ElasticOccurrenceService extends ElasticSearchService
{
    public function __construct(array $config, string $indexPrefix)
    {
        parent::__construct($config, $indexPrefix);
        $this->type = $this->getIndex('occurrences')->getType('occurrence');
    }

    public function setupOccurrences(): void
    {
        $index = $this->getIndex('occurrences');
        if ($index->exists()) {
            $index->delete();
        }
        // Configure analysis: remove parentheses and square brackets
        $index->create([
            'analysis' => [
                'filter' => [
                    'greek_stemmer' => [
                        'type' => 'stemmer',
                        'language' => 'greek',
                    ],
                ],
                'char_filter' => [
                    'remove_par_brackets_filter' => [
                        'type' => 'mapping',
                        'mappings' => [
                            '( =>',
                            ') =>',
                            '[ =>',
                            '] =>',
                            '< =>',
                            '> =>',
                        ],
                    ],
                ],
                'analyzer' => [
                    'custom_greek' => [
                        'tokenizer' => 'icu_tokenizer',
                        'char_filter' => [
                            'remove_par_brackets_filter'
                        ],
                        'filter' => [
                            'icu_folding',
                            'lowercase',
                            'greek_stemmer',
                        ],
                    ],
                ],
            ],
        ]);
        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        $mapping->setProperties(
            [
                'text' => [
                    'type' => 'text',
                    'analyzer' => 'custom_greek',
                ],
                'subject' => ['type' => 'nested'],
                'manuscript_content' => ['type' => 'nested'],
                'patron' => ['type' => 'nested'],
                'scribe' => ['type' => 'nested'],
            ]
        );
        $mapping->send();
    }

    public function addOccurrences(array $occurrences): void
    {
        $occurrencesElastic = [];
        foreach ($occurrences as $occurrence) {
            $occurrencesElastic [] = $occurrence->getElastic();
        }

        $this->bulkAdd($occurrencesElastic);
    }

    public function addOccurrence(Occurrence $occurrence): void
    {
        $this->add($occurrence->getElastic());
    }

    public function delOccurrence(Occurrence $occurrence): void
    {
        $this->del($occurrence->getId());
    }

    public function searchAndAggregate(array $params): array
    {
        if (!empty($params['filters'])) {
            $params['filters'] = self::classifyFilters($params['filters']);
        }

        $result = $this->search($params);

        $aggregation_result = $this->aggregate(
            self::classifyFilters(['meter', 'subject', 'manuscript_content', 'patron', 'scribe', 'genre', 'public']),
            !empty($params['filters']) ? $params['filters'] : []
        );

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
                    case 'text':
                        $result['text'][$key] = [
                            'text' => $value,
                            'type' => $filters['text_type'],
                        ];
                        break;
                    case 'meter':
                    case 'manuscript':
                    case 'genre':
                        if (is_int($key)) {
                            $result['object'][] = $value;
                        } else {
                            $result['object'][$key] = $value;
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
                    case 'subject':
                    case 'manuscript_content':
                    case 'patron':
                    case 'scribe':
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
