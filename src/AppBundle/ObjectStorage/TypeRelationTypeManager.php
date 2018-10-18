<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\TypeRelationType;

use AppBundle\Utils\ArrayToJson;

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
        $rawTypeRelationTypes = $this->dbs->getTypeRelationTypesByIds($ids);
        return $this->getWithData($rawTypeRelationTypes);
    }

    /**
     * Get single type relation types with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
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

    /**
     * Get all type relation types with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'type_relation_types',
            ['type_relation_types'],
            function () {
                $rawIds = $this->dbs->getIds();
                $ids = self::getUniqueIds($rawIds, 'type_relation_type_id');
                $typeRelationTypes = $this->get($ids);

                return ArrayToJson::arrayToShortJson($typeRelationTypes);
            }
        );
    }
}
