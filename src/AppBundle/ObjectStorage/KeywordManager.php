<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Keyword;

class KeywordManager extends ObjectManager
{
    public function getKeywordsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'keyword');
        if (empty($ids)) {
            return $cached;
        }

        $keywords = [];
        $rawKeywords = $this->dbs->getKeywordsByIds($ids);

        foreach ($rawKeywords as $rawKeyword) {
            $keywords[$rawKeyword['keyword_id']] = new Keyword(
                $rawKeyword['keyword_id'],
                $rawKeyword['name']
            );
        }

        $this->setCache($keywords, 'keyword');

        return $cached + $keywords;
    }
}
