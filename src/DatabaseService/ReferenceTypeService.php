<?php

namespace App\DatabaseService;

use Doctrine\DBAL\Connection;

class ReferenceTypeService extends DatabaseService
{
    /**
     * Get all reference type ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                reference_type.idreference_type as reference_type_id
            from data.reference_type'
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getReferenceTypesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference_type.idreference_type as reference_type_id,
                reference_type.type as name
            from data.reference_type
            where reference_type.idreference_type in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
