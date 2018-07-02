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
}
