<?php

namespace AppBundle\Service\ElasticSearchService;

/**
 */
class GreekAnalysis
{
    /**
     * Elasticsearch config for Greek Analysis
     * @var array
     */
    const ANALYSIS = [
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
                        '| =>',
                        '+ =>',
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
            'normalizer' => [
                'custom_greek' => [
                    'filter' => [
                        'icu_folding',
                        'lowercase',
                    ],
                ],
            ],
        ],
    ];
}
