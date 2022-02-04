<?php

namespace App\ObjectStorage;

abstract class ListEntityManager extends EntityManager
{
    abstract public function get(array $ids): array;

    protected function getAllCombined(
        string $level,
        string $sortFunction = null
    ): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, $this->entityType . '_id');

        $objects = $this->get($ids);

        if (!empty($sortFunction)) {
            usort(
                $objects,
                function ($a, $b) use ($sortFunction) {
                    if ($sortFunction == 'getId') {
                        return $a->{$sortFunction}() > $b->{$sortFunction}();
                    }
                    return strcmp($a->{$sortFunction}(), $b->{$sortFunction}());
                }
            );
        }

        return $objects;
    }
}