<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class IdentifierService extends DatabaseService
{
    public function getIdentifiersByType(string $type): array
    {
        return $this->conn->executeQuery(
            'SELECT
                identifier.ididentifier as identifier_id,
                identifier.system_name,
                identifier.name,
                identifier.is_primary,
                array_length(identifier.ids, 1) as volumes,
                identifier.regex,
                identifier.description
            from data.identifier
            where identifier.type = ?
            order by identifier.order',
            [$type]
        )->fetchAll();
    }
}
