<?php

namespace App\ObjectStorage;

class ElasticManagers
{
    const MANAGERS = [
        'manuscript' => ManuscriptManager::class,
        'occurrence' => OccurrenceManager::class,
        'type' => TypeManager::class,
        'person' => PersonManager::class,
        'article' => ArticleManager::class,
        'bib_varia' => BibVariaManager::class,
        'book' => BookManager::class,
        'book_chapter' => BookChapterManager::class,
        'book_cluster' => BookClusterManager::class,
        'book_series' => BookSeriesManager::class,
        'blog' => BlogManager::class,
        'blog_post' => BlogPostManager::class,
        'online_source' => OnlineSourceManager::class,
        'phd' => PhdManager::class,
    ];
}