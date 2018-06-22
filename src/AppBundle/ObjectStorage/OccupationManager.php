<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Occupation;

class OccupationManager extends ObjectManager
{
    public function getOccupationsByIds(array $ids)
    {
        list($cached, $ids) = $this->getCache($ids, 'occupation');
        if (empty($ids)) {
            return $cached;
        }

        $occupations = [];
        $rawOccupations = $this->dbs->getOccupationsByIds($ids);

        foreach ($rawOccupations as $rawOccupation) {
            $occupations[$rawOccupation['occupation_id']] = new Occupation(
                $rawOccupation['occupation_id'],
                $rawOccupation['name'],
                $rawOccupation['is_function']
            );
        }

        $this->setCache($occupations, 'occupation');

        return $cached + $occupations;
    }

    public function getAllOccupations(): array
    {
        $cache = $this->cache->getItem('occupations');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $occupations = [];
        $rawOccupations = $this->dbs->getAllOccupations();

        foreach ($rawOccupations as $rawOccupation) {
            $occupations[] = new Occupation(
                $rawOccupation['occupation_id'],
                $rawOccupation['name'],
                $rawOccupation['is_function']
            );
        }

        // Sort by name
        usort($occupations, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['occupations']);
        $this->cache->save($cache->set($occupations));
        return $occupations;
    }
}
