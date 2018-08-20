<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Keyword;

class KeywordManager extends ObjectManager
{
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Keyword::CACHENAME,
            $ids,
            function ($ids) {
                $keywords = [];
                $rawKeywords = $this->dbs->getKeywordsByIds($ids);

                foreach ($rawKeywords as $rawKeyword) {
                    $keywords[$rawKeyword['keyword_id']] = new Keyword(
                        $rawKeyword['keyword_id'],
                        $rawKeyword['name']
                    );
                }

                return $keywords;
            }
        );
    }
}
