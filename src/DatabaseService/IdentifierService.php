<?php

namespace App\DatabaseService;

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
                identifier.extra_required,
                book.idcluster as cluster_id
            from data.identifier
            left join data.book on identifier.ids[1] = book.identity
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
                identifier.extra_required,
                book.idcluster as cluster_id
            from data.identifier
            left join data.book on identifier.ids[1] = book.identity
            where ? = ANY(identifier.type)
            order by identifier.order',
            [$type]
        )->fetchAll();
    }
}
