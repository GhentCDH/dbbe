<?php

namespace App\DatabaseService;

class PageService extends DatabaseService
{
    public function getBySlug(string $slug)
    {
        return $this->conn->executeQuery(
            'SELECT slug, title, content, display_navigation from logic.page
            where slug = ?
            order by revision desc
            limit 1',
            [
                $slug,
            ]
        )->fetch();
    }

    public function update(int $userid, string $slug, string $title, string $content, bool $displayNavigation): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO logic.page (iduser, revision, slug, title, content, display_navigation)
            values (
                ?,
                (select max(revision) + 1 from logic.page where slug = ?),
                ?,
                ?,
                ?,
                ?
            )',
            [
                $userid,
                $slug,
                $slug,
                $title,
                $content,
                $displayNavigation ? 'TRUE': 'FALSE'
            ]
        );
    }
}
