<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class StatusService extends DatabaseService
{
    public function getStatusesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                status.idstatus as status_id,
                status.status as status_name,
                status.type as status_type
            from data.status
            where status.idstatus in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getAllStatuses(): array
    {
        return $this->conn->query(
            'SELECT
                status.idstatus as status_id,
                status.status as status_name,
                status.type as status_type
            from data.status'
        )->fetchAll();
    }

    public function insert(string $name, string $type): int
    {
        $this->conn->executeUpdate(
            'INSERT INTO data.status (status, type)
            values (?, ?)',
            [
                $name,
                $type,
            ]
        );
        return $this->conn->executeQuery(
            'SELECT
                status.idstatus as status_id
            from data.status
            order by idstatus desc
            limit 1'
        )->fetch()['status_id'];
    }

    public function updateName(int $statusId, string $name): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.status
            set status = ?
            where status.idstatus = ?',
            [$name, $statusId]
        );
    }

    public function delete(int $statusId): int
    {
        // don't delete if this status is used in document_status
        $count = $this->conn->executeQuery(
            'SELECT count(*)
            from data.document_status
            where document_status.idstatus = ?',
            [$statusId]
        )->fetchColumn(0);
        if ($count > 0) {
            throw new DependencyException('This status has dependencies.');
        }
        return $this->conn->executeUpdate(
            'DELETE from data.status
            where status.idstatus = ?',
            [
                $statusId,
            ]
        );
    }
}
