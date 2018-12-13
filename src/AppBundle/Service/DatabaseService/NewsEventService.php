<?php

namespace AppBundle\Service\DatabaseService;

use DateTime;
use Exception;

class NewsEventService extends DatabaseService
{
    public function getAll()
    {
        return $this->conn->executeQuery(
            'SELECT
                id,
                title,
                link as url,
                date,
                public,
                "order"
            from logic.news_event
            order by "order" asc'
        )->fetchAll();
    }

    public function insert(int $userId, string $title, string $url, string $date, bool $public, int $order): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO logic.news_event
            (iduser, title, link, "date", public, "order")
            values (?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $title,
                $url,
                $date,
                $public ? 'TRUE' : 'FALSE',
                $order
            ]
        );
    }

    public function update(int $id, int $userId, string $title, string $url, string $date, bool $public, int $order): int
    {
        return $this->conn->executeUpdate(
            'UPDATE logic.news_event
            set iduser = ?, title = ?, link = ?, "date" = ?, public = ?, "order" = ?
            where id = ?',
            [
                $userId,
                $title,
                $url,
                $date,
                $public ? 'TRUE' : 'FALSE',
                $order,
                $id,
            ]
        );
    }

    public function delete(int $id): int
    {
        return $this->conn->executeUpdate(
            'DELETE from logic.news_event
            where id = ?',
            [
                $id,
            ]
        );
    }
}
