<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class StatusService extends DatabaseService
{
    public function getAllManuscriptStatuses(): array
    {
        return $this->conn->query(
            'SELECT
                status.idstatus as status_id,
                status.status as status_name
            from data.status
            where status.type = \'manuscript\''
        )->fetchAll();
    }
}
