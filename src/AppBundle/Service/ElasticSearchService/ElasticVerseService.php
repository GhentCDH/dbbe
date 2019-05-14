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
        $index->create(Analysis::ANALYSIS);

        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        $properties = [
            'verse' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_stemmer',
            ],
        ];
        $mapping->setProperties($properties);
        $mapping->send();
    }

    public function searchVerse(string $verse, int $id = null, bool $init = false): array
    {
        $results = [];

        // Verses that have a group
        $filterArray = [
            'text' => [
                'verse' => [
                    'text' => $verse,
                ],
            ],
        ];
        if ($init) {
            $filterArray['text']['verse']['init'] = true;
        } else {
            $filterArray['text']['verse']['combination'] = 'any';
        }
        $queryQuery = self::createQuery($filterArray)
            ->addMust(new Query\Exists('group_id'));
        // Eliminate current verse
        if (!$init && $id != null) {
            $queryQuery->addMustNot(new Query\Match('id', $id));
        }

        $aggregation = (new Aggregation\Terms('verses_grouped'))
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
            );
        $aggregation->setSize(self::MAX_SEARCH);

        $query = (new Query())
            ->setQuery($queryQuery)
            // Only aggregation will be used
            ->setSize(0)
            ->addAggregation($aggregation);

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
        if (!$init && $id != null) {
            $queryQuery->addMustNot(new Query\Match('id', $id));
        }

        $query = (new Query())
            ->setQuery($queryQuery)
            ->setHighlight(self::createHighlight($filterArray))
            ->setSize(25);
        $query->setSize(self::MAX_SEARCH);

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

    public function initVerseGroups(int $offset): array
    {
        // Get all verses
        $query = (new Query())
            ->setQuery((self::createQuery([]))
                ->addMustNot(new Query\Exists('group_id')))
            ->setSort(['id' => 'asc'])
            ->setSize(10)
            ->setFrom($offset * 10);
        $verses = [];
        foreach ($this->type->search($query)->getResponse()->getData()['hits']['hits'] as $row) {
            $verses[] = $row['_source'];
        }

        // Find matches
        $matchedIds = [];
        $groups = [];
        foreach ($verses as $verse) {
            if (in_array($verse['id'], $matchedIds)) {
                continue;
            }
            $matches = $this->searchVerse($verse['verse'], $verse['id'], true);
            $group = [];
            foreach ($matches as $match) {
                foreach ($match['group'] as $matchedVerse) {
                    $matchedIds[] = $matchedVerse['id'];
                }
                $group[] = $match['group'];
            }
            $groups[] = $group;
        }

        return $groups;
    }
}
