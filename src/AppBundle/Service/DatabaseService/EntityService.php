<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class EntityService extends DatabaseService
{
    public function getPublics(array $ids = null): array
    {
        return $this->conn->executeQuery(
            'SELECT
                entity.identity as entity_id,
                entity.public
            from data.entity
            where entity.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getComments(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                entity.identity as entity_id,
                entity.public_comment,
                entity.private_comment
            from data.entity
            where entity.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getIdentifications(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                global_id.idsubject as entity_id,
                identifier.ididentifier as identifier_id,
                identifier.system_name,
                identifier.name,
                identifier.is_primary,
                identifier.link,
                array_to_json(array_agg(global_id.identifier ORDER BY array_position(identifier.ids, global_id.idauthority))) as identifiers,
                array_to_json(array_agg(global_id.idauthority ORDER BY array_position(identifier.ids, global_id.idauthority))) as authority_ids,
                array_to_json(identifier.ids) as identifier_ids
            from data.global_id
            inner join data.identifier on global_id.idauthority = ANY(identifier.ids)
            where global_id.idsubject in (?)
            group by global_id.idsubject, identifier.ididentifier
            order by identifier.order',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function updatePublic(int $entityId, bool $public): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set public = ?
            where entity.identity = ?',
            [
                $public ? 'TRUE': 'FALSE',
                $entityId,
            ]
        );
    }

    public function insertDate(int $entityId, string $type, string $date): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.factoid (subject_identity, date, idfactoid_type)
            values (
                ?,
                ?,
                (
                    select
                        factoid_type.idfactoid_type
                    from data.factoid_type
                    where factoid_type.type = ?
                )
            )',
            [
                $entityId,
                $date,
                $type,
            ]
        );
    }

    public function updateDate(int $entityId, string $type, string $date): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set date = ?
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = ?',
            [
                $date,
                $entityId,
                $type,
            ]
        );
    }

    public function deleteDate(int $entityId, string $type): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.factoid
            using data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = ?',
            [
                $entityId,
                $type,
            ]
        );
    }

    public function updatePublicComment(int $entityId, string $publicComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set public_comment = ?
            where entity.identity = ?',
            [
                $publicComment,
                $entityId,
            ]
        );
    }

    public function updatePrivateComment(int $entityId, string $privateComment): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set private_comment = ?
            where entity.identity = ?',
            [
                $privateComment,
                $entityId,
            ]
        );
    }

    public function delIdentification(int $entityId, int $identifierId, int $volume)
    {
        return $this->conn->executeUpdate(
            'DELETE from data.global_id
            using data.identifier
            where global_id.idsubject = ?
            and global_id.idauthority = identifier.ids[? + 1]
            and identifier.ididentifier = ?',
            [
                $entityId,
                $volume,
                $identifierId,
            ]
        );
    }

    public function upsertIdentification(int $entityId, int $identifierId, int $volume, string $identification): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.global_id (idauthority, idsubject, identifier)
            VALUES (
                (
                    select identifier.ids[? + 1]
                    from data.identifier
                    where identifier.ididentifier = ?
                ),
                ?,
                ?
            )
            -- primary key constraint on idauthority, idsubject
            on conflict (idauthority, idsubject) do update
            set identifier = excluded.identifier',
            [
                $volume,
                $identifierId,
                $entityId,
                $identification,
            ]
        );
    }
}
