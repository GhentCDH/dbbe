<?php

namespace App\ElasticSearchService;

use Elastica\Aggregation;
use Elastica\Mapping;
use Elastica\Query;
use Elastica\Script;

use App\DatabaseService\DatabaseServiceInterface;

/**
 * Setup and configure search for verses
 * Will not be used by anonymous people
 */
class ElasticVerseService extends ElasticBaseService
{
    private $dbs;

    public function __construct(array $config, string $indexPrefix, DatabaseServiceInterface $databaseService)
    {
        parent::__construct(
            $config,
            $indexPrefix,
            'verses'
        );

        $this->dbs = $databaseService;
    }

    public function setupVerses(): void
    {
        if ($this->index->exists()) {
            $this->index->delete();
        }
        // Configure analysis
        $this->index->create(['settings' => Analysis::ANALYSIS]);

        $properties = [
            'verse' => [
                'type' => 'text',
                'analyzer' => 'custom_greek_stemmer',
            ],
        ];
        $this->index->setMapping(new Mapping($properties));
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
            $queryQuery->addMustNot(new Query\MatchQuery('id', $id));
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
        if ($init) {
            // Display all groups
            $aggregation->setSize(self::MAX_SEARCH);
        } else {
            // Display only first 10 groups
            $aggregation->setSize(10);
        }

        $query = (new Query())
            ->setQuery($queryQuery)
            // Only aggregation will be used
            ->setSize(0)
            ->addAggregation($aggregation);

        $groupResults = $this->index->search($query)->getAggregation('verses_grouped')['buckets'];
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
            $queryQuery->addMustNot(new Query\MatchQuery('id', $id));
        }

        $query = (new Query())
            ->setQuery($queryQuery)
            ->setHighlight(self::createHighlight($filterArray))
            ->setSize(25);
        if ($init) {
            // Display all verses
            $query->setSize(self::MAX_SEARCH);
        } else {
            // Display only first 10 verses
            $query->setSize(10);
        }

        $noGroupResults = $this->index->search($query)->getResponse()->getData()['hits']['hits'];

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
        $rawVerses = $this->dbs->getUngroupedVerses(10, $offset * 10);

        // Find matches
        $matchedIds = [];
        $groups = [];
        foreach ($rawVerses as $rawVerse) {
            if (in_array($rawVerse['verse_id'], $matchedIds)) {
                continue;
            }
            $matches = $this->searchVerse($rawVerse['verse'], $rawVerse['verse_id'], true);
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
