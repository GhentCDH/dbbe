<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Type;

class TypeManager extends DocumentManager
{
    /**
     * Get types with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Type::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $types = [];
                $rawIncipits = $this->dbs->getIncipits($ids);
                if (count($rawIncipits) == 0) {
                    return [];
                }
                foreach ($rawIncipits as $rawIncipit) {
                    $types[$rawIncipit['type_id']] = (new Type())
                        ->setId($rawIncipit['type_id'])
                        ->setIncipit($rawIncipit['incipit']);
                }

                // number of verses
                $rawVerses = $this->dbs->getNumberOfVerses($ids);
                if (count($rawVerses) > 0) {
                    foreach ($rawVerses as $rawVerse) {
                        $types[$rawVerse['type_id']]
                            ->setNumberOfVerses($rawVerse['verses']);
                    }
                }

                $this->setPublics($types);

                return $types;
            }
        );
    }
}
