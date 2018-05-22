<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Status;

class StatusManager extends ObjectManager
{
    public function getAllManuscriptStatuses()
    {
        $cache = $this->cache->getItem('manuscript_statuses');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawStatuses = $this->dbs->getAllManuscriptStatuses();
        $statuses = [];
        foreach ($rawStatuses as $rawStatus) {
            $statuses[] = new Status($rawStatus['status_id'], $rawStatus['status_name']);
        }

        // Sort by name
        usort($statuses, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['statuses']);
        $this->cache->save($cache->set($statuses));
        return $statuses;
    }
}
