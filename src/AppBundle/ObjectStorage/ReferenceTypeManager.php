<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\ReferenceType;

/**
 * ObjectManager for reference types
 * Servicename: reference_type_manager
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
        return $this->wrapCache(
            ReferenceType::CACHENAME,
            $ids,
            function ($ids) {
                $referenceTypes = [];
                $rawReferenceTypes = $this->dbs->getReferenceTypesByIds($ids);
                $referenceTypes = $this->getWithData($rawReferenceTypes);

                return $referenceTypes;
            }
        );
    }

    /**
     * Get single reference types with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            ReferenceType::CACHENAME,
            $data,
            'reference_type_id',
            function ($data) {
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
        );
    }

    /**
     * Get all reference types with all information
     * @return array
     */
    public function getAll(): array
    {
        return $this->wrapArrayCache(
            'reference_types',
            ['reference_types'],
            function () {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, 'reference_type_id');
                $referenceTypes = $this->get($ids);

                return $referenceTypes;
            }
        );
    }
}
