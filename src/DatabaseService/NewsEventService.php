<?php

namespace App\DatabaseService;

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
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getPublicDateSorted(): array
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
where public = 'TRUE'
order by date desc
SQL
        )->fetchAll();
    }

    /**
     * @param string $userEmail
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
        string $userEmail,
        string $title,
        string $url = null,
        string $date,
        bool $public,
        int $order,
        string $abstract = null,
        string $text = null
    ): int {
        return $this->conn->executeUpdate(
            <<<'SQL'
insert into logic.news_event
(user_email, title, url, "date", public, "order", abstract, "text")
values (?, ?, ?, ?, ?, ?, ?, ?)
SQL
,
            [
                $userEmail,
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
     * @param string $userEmail
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
        string $userEmail,
        string $title,
        string $url = null,
        string $date,
        bool $public,
        int $order,
        string $abstract = null,
        string $text = null
    ): int {
        return $this->conn->executeUpdate(
            <<<'SQL'
update logic.news_event
set user_email = ?, title = ?, url = ?, "date" = ?, public = ?, "order" = ?, abstract = ?, "text" = ?
where id = ?
SQL
,
            [
                $userEmail,
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
