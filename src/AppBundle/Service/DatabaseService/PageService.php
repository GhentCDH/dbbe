<?php

namespace AppBundle\Service\DatabaseService;

class PageService extends DatabaseService
{
    public function getBySlug(string $slug): array
    {
        return $this->conn->executeQuery(
            'SELECT slug, title, content, display_navigation from logic.page
            where slug = ?
            order by revision desc',
            [
                $slug,
            ]
        )->fetch();
    }

    public function update(int $userid, string $slug, string $title, string $content, bool $nav): int
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
                $nav
            ]
        );
    }
}
