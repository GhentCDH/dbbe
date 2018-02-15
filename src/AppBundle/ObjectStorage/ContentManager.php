<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Content;
use AppBundle\Model\ContentWithParents;

class ContentManager extends ObjectManager
{
    public function getContentsWithParentsByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'content_with_parents');
        if (empty($ids)) {
            return $cached;
        }

        $contentsWithParents = [];
        $rawContentsWithParents = $this->dbs->getContentsWithParentsByIds($ids);

        foreach ($rawContentsWithParents as $rawContentWithParents) {
            $ids = explode(':', $rawContentWithParents['ids']);
            $names = explode(':', $rawContentWithParents['names']);

            $contents = [];
            foreach (array_keys($ids) as $key) {
                $contents[] = new Content($ids[$key], $names[$key]);
            }
            $contentWithParents = new ContentWithParents($contents);

            foreach ($ids as $id) {
                $contentWithParents->addCacheDependency('content.' . $id);
            }

            $contentsWithParents[$contentWithParents->getId()] = $contentWithParents;
        }

        $this->setCache($contentsWithParents, 'content_with_parents');

        return $cached + $contentsWithParents;
    }

    public function getAllContents(): array
    {
        $cache = $this->cache->getItem('contents');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getContentIds();
        $ids = self::getUniqueIds($rawIds, 'content_id');
        $contentsWithParents = $this->getContentsWithParentsByIds($ids);

        // Sort by name
        usort($contentsWithParents, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag('contents');
        $this->cache->save($cache->set($contentsWithParents));
        return $contentsWithParents;
    }
}
