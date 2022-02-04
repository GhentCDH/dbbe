<?php

namespace App\ObjectStorage;

use App\Model\ReferenceType;

use App\Utils\ArrayToJson;

/**
 * ObjectManager for reference types
 */
class ReferenceTypeManager extends ObjectManager
{
    /**
     * Get single reference types with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $rawReferenceTypes = $this->dbs->getReferenceTypesByIds($ids);
        return $this->getWithData($rawReferenceTypes);
    }

    /**
     * Get single reference types with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        $referenceTypes = [];
        foreach ($data as $rawReferenceType) {
            if (isset($rawReferenceType['reference_type_id'])
                && !isset($referenceTypes[$rawReferenceType['reference_type_id']])
            ) {
                $referenceTypes[$rawReferenceType['reference_type_id']] = new ReferenceType(
                    $rawReferenceType['reference_type_id'],
                    $rawReferenceType['name']
                );
            }
        }

        return $referenceTypes;
    }

    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'reference_type_id');
        $referenceTypes = $this->get($ids);

        return $referenceTypes;
    }

    /**
     * Get all reference types with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'reference_types',
            ['reference_types'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getAll());
            }
        );
    }
}
