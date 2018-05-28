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
    public function getMiniTypesByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'type_mini');
        if (empty($ids)) {
            return $cached;
        }

        $types = [];
        $rawIncipits = $this->dbs->getIncipits($ids);
        if (count($rawIncipits) == 0) {
            return $cached;
        }
        foreach ($rawIncipits as $rawIncipit) {
            $types[$rawIncipit['type_id']] = (new Type())
                ->setId($rawIncipit['type_id'])
                ->setIncipit($rawIncipit['incipit']);
        }

        $this->setPublics($types, $ids);

        $this->setCache($types, 'type_mini');

        return $cached + $types;
    }
}
