<?php

namespace AppBundle\Service\DatabaseService;

class NewsEventService extends DatabaseService
{
    /**
     * @param int $id
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getSingle(int $id): array
    {
        return $this->conn->executeQuery(
            <<<'SQL'
select
    id,
    title,
    url,
    date,
    public,
    "order",
    abstract,
    "text"
from logic.news_event
where id = ?
SQL
,
            [
                $id,
            ]
        )->fetch();
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getAll(): array
    {
        return $this->conn->executeQuery(
            <<<'SQL'
select
    id,
    title,
    url,
    date,
    public,
    "order",
    abstract,
    "text"
from logic.news_event
order by "order" asc
SQL
        )->fetchAll();
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getThree(): array
    {
        return $this->conn->executeQuery(
            <<<'SQL'
select
    id,
    title,
    url,
    date,
    public,
    "order",
    abstract,
    "text"
from logic.news_event
order by "order" asc
limit 3
SQL
        )->fetchAll();
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getThreePublic(): array
    {
        return $this->conn->executeQuery(
            <<<'SQL'
select
    id,
    title,
    url,
    date,
    public,
    "order",
    abstract,
    "text"
from logic.news_event
where public
order by "order" asc
limit 3
SQL
        )->fetchAll();
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getDateSorted(): array
    {
        return $this->conn->executeQuery(
            <<<'SQL'
select
    id,
    title,
    url,
    date,
    public,
    "order",
    abstract,
    "text"
from logic.news_event
order by date desc
SQL
        )->fetchAll();
    }

    /**
     * @param int $userId
     * @param string $title
     * @param string $url
     * @param string $date
     * @param bool $public
     * @param int $order
     * @param string $abstract
     * @param string $text
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function insert(
        int $userId,
        string $title,
        string $url,
        string $date,
        bool $public,
        int $order,
        string $abstract,
        string $text
    ): int {
        return $this->conn->executeUpdate(
            <<<'SQL'
insert into logic.news_event
(iduser, title, url, "date", public, "order", abstract, "text")
values (?, ?, ?, ?, ?, ?, ?, ?)
SQL
,
            [
                $userId,
                $title,
                $url,
                $date,
                $public ? 'TRUE' : 'FALSE',
                $order,
                $abstract,
                $text,
            ]
        );
    }

    /**
     * @param int $id
     * @param int $userId
     * @param string $title
     * @param string $url
     * @param string $date
     * @param bool $public
     * @param int $order
     * @param string $abstract
     * @param string $text
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update(
        int $id,
        int $userId,
        string $title,
        string $url,
        string $date,
        bool $public,
        int $order,
        string $abstract,
        string $text
    ): int {
        return $this->conn->executeUpdate(
            <<<'SQL'
update logic.news_event
set iduser = ?, title = ?, url = ?, "date" = ?, public = ?, "order" = ?, abstract = ?, "text" = ? 
where id = ?
SQL
,
            [
                $userId,
                $title,
                $url,
                $date,
                $public ? 'TRUE' : 'FALSE',
                $order,
                $abstract,
                $text,
                $id,
            ]
        );
    }

    /**
     * @param int $id
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function delete(int $id): int
    {
        return $this->conn->executeUpdate(
            <<<'SQL'
delete from logic.news_event
where id = ?
SQL
,
            [
                $id,
            ]
        );
    }
}
