<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Aggregation;
use Elastica\Query;
use Elastica\Script;
use Elastica\Type;

/**
 * Setup and configure search for verses
 * Will not be used by anonymous people
 */
class ElasticVerseService extends ElasticBaseService
{
    public function __construct(array $config, string $indexPrefix)
    {
        parent::__construct(
            $config,
            $indexPrefix,
            'verses',
            'verse'
        );
    }

    public function setupVerses(): void
    {
        $index = $this->getIndex();
        if ($index->exists()) {
            $index->delete();
        }
        // Configure analysis
        $index->create(GreekAnalysis::ANALYSIS);

        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        $properties = [
            'verse' => [
                'type' => 'text',
                'analyzer' => 'custom_greek',
            ],
        ];
        $mapping->setProperties($properties);
        $mapping->send();
    }

    public function searchVerse(string $verse, int $id = null): array
    {
        $results = [];

        // Verses that have a group
        $filterArray = [
            'text' => [
                'verse' => [
                    'text' => $verse,
                    'type' => 'any',
                ],
            ],
        ];
        $queryQuery = self::createQuery($filterArray)
            ->addMust(new Query\Exists('group_id'));
        // Eliminate current verse
        if ($id != null) {
            $queryQuery->addMustNot(new Query\Match('id', $id));
        }

        $query = (new Query())
            ->setQuery($queryQuery)
            // Only aggregation will be used
            ->setSize(0)
            ->addAggregation(
                (new Aggregation\Terms('verses_grouped'))
                    // Display only first 10 groups
                    ->setSize(10)
                    ->setField('group_id')
                    ->setOrder('top_score', 'desc')
                    ->addAggregation(
                        (new Aggregation\Max('top_score'))
                            ->setScript(new Script\Script('_score'))
                    )
                    ->addAggregation(
                        (new Aggregation\TopHits('verses'))
                            ->setHighlight(self::createHighlight($filterArray))
                            // Display top 5 verses for each group
                            ->setSize(5)
                    )
            );

        $groupResults = $this->type->search($query)->getAggregation('verses_grouped')['buckets'];
        foreach ($groupResults as $result) {
            $cleanResult = [
                '_score' => $result['top_score']['value'],
                'total' => $result['verses']['hits']['total'],
                'group_id' => $result['verses']['hits']['hits'][0]['_source']['group_id'],
                'group' => [],
            ];
            foreach ($result['verses']['hits']['hits'] as $verse) {
                if (isset($verse['highlight'])) {
                    $verse['_source']['highlight_verse'] = $verse['highlight']['verse'][0];
                }
                $cleanResult['group'][] = $verse['_source'];
            }
            $results[] = $cleanResult;
        }

        // Verses without a group
        $queryQuery = self::createQuery($filterArray)
            ->addMustNot(new Query\Exists('group_id'));
        // Eliminate current verse
        if ($id != null) {
            $queryQuery->addMustNot(new Query\Match('id', $id));
        }

        $query = (new Query())
            ->setQuery($queryQuery)
            ->setHighlight(self::createHighlight($filterArray))
            // Display only first 10 verses
            ->setSize(10);

        $noGroupResults = $this->type->search($query)->getResponse()->getData()['hits']['hits'];

        foreach ($noGroupResults as $result) {
            $result['_source']['_score'] = $result['_score'];
            if (isset($result['highlight'])) {
                $result['_source']['highlight_verse'] = $result['highlight']['verse'][0];
            }
            $results[] = [
                '_score' => $result['_score'],
                'group' => array($result['_source']),
            ];
        }

        usort(
            $results,
            function ($a, $b) {
                return $a['_score'] < $b['_score'];
            }
        );

        return $results;
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
                    case 'verse':
                        $result['text'][$key] = [
                            'text' => $value,
                            'type' => $filters['text_type'],
                        ];
                        break;
                }
            }
        }
        return $result;
    }
}
