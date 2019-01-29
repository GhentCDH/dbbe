<?php

namespace AppBundle\Service\ElasticSearchService;

/**
 */
class Analysis
{
    /**
     * Elasticsearch config for Special Analysis
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
                'custom_greek_stemmer' => [
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
                'custom_greek_original' => [
                    'tokenizer' => 'icu_tokenizer',
                    'char_filter' => [
                        'remove_par_brackets_filter'
                    ],
                    'filter' => [
                        'icu_folding',
                        'lowercase',
                    ],
                ],
            ],
            'normalizer' => [
                'case_insensitive' => [
                    'filter' => [
                        'lowercase',
                    ],
                ],
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
