<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class IdentifierService extends DatabaseService
{
    public function getIdentifiersByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                identifier.ididentifier as identifier_id,
                identifier.system_name,
                identifier.name,
                identifier.is_primary,
                identifier.link,
                identifier.link_type,
                array_to_json(identifier.ids) as ids,
                identifier.regex,
                identifier.description,
                identifier.extra,
                identifier.extra_required
            from data.identifier
            where identifier.ididentifier in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getByType(string $type): array
    {
        return $this->conn->executeQuery(
            'SELECT
                identifier.ididentifier as identifier_id,
                identifier.system_name,
                identifier.name,
                identifier.is_primary,
                identifier.link,
                identifier.link_type,
                array_to_json(identifier.ids) as ids,
                identifier.regex,
                identifier.description,
                identifier.extra,
                identifier.extra_required
            from data.identifier
            where ? = ANY(identifier.type)
            order by identifier.order',
            [$type]
        )->fetchAll();
    }
}
