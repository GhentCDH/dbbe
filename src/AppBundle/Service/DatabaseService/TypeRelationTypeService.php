<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class TypeRelationTypeService extends DatabaseService
{
    /**
     * Get all type relation ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                factoid_type.idfactoid_type as type_relation_type_id
            from data.factoid_type
            where factoid_type.group = \'reconstructed_poem_related_to_reconstructed_poem\''
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getTypeRelationTypesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                factoid_type.idfactoid_type as type_relation_type_id,
                factoid_type.type as name
            from data.factoid_type
            where factoid_type.idfactoid_type in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
