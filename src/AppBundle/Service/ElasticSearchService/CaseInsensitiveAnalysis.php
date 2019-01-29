<?php

namespace AppBundle\Service\ElasticSearchService;

/**
 */
class CaseInsensitiveAnalysis
{
    /**
     * Elasticsearch config for Case Insensitive Sorting
     * @var array
     */
    const ANALYSIS = [
        'analysis' => [
            'normalizer' => [
                'case_insensitive' => [
                    'filter' => [
                        'lowercase',
                    ],
                ],
            ],
        ],
    ];
}
