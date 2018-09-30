<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\TypeRelationType;

/**
 * ObjectManager for type relation types
 * Servicename: type_relation_type_manager
 */
class TypeRelationTypeManager extends ObjectManager
{
    /**
     * Get single type relation types with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        return $this->wrapCache(
            TypeRelationType::CACHENAME,
            $ids,
            function ($ids) {
                $typeRelationTypes = [];
                $rawTypeRelationTypes = $this->dbs->getTypeRelationTypesByIds($ids);
                $typeRelationTypes = $this->getWithData($rawTypeRelationTypes);

                return $typeRelationTypes;
            }
        );
    }

    /**
     * Get single type relation types with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            TypeRelationType::CACHENAME,
            $data,
            'type_relation_type_id',
            function ($data) {
                $typeRelationTypes = [];
                foreach ($data as $rawTypeRelationType) {
                    if (isset($rawTypeRelationType['type_relation_type_id'])
                        && !isset($typeRelationTypes[$rawTypeRelationType['type_relation_type_id']])
                    ) {
                        $typeRelationTypes[$rawTypeRelationType['type_relation_type_id']] = new TypeRelationType(
                            $rawTypeRelationType['type_relation_type_id'],
                            $rawTypeRelationType['name']
                        );
                    }
                }

                return $typeRelationTypes;
            }
        );
    }

    /**
     * Get all type relation types with all information
     * @return array
     */
    public function getAll(): array
    {
        return $this->wrapArrayCache(
            'type_relation_types',
            ['type_relation_types'],
            function () {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, 'type_relation_type_id');
                $typeRelationTypes = $this->get($ids);

                return $typeRelationTypes;
            }
        );
    }
}
