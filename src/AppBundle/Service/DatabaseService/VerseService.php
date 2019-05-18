<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class VerseService extends DatabaseService
{
    public function getIds(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem_verse.id as verse_id
            from data.original_poem_verse'
        )->fetchAll();
    }

    public function getUngroupedVerses(int $limit, int $offset): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem_verse.id as verse_id,
                original_poem_verse.idgroup as group_id,
                original_poem_verse.verse,
                original_poem_verse.order
            from data.original_poem_verse
            where idgroup is null
            order by id asc
            limit ?
            offset ?',
            [
                $limit,
                $offset,
            ]
        )->fetchAll();
    }

    public function getBasicInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem_verse.id as verse_id,
                original_poem_verse.idgroup as group_id,
                original_poem_verse.verse,
                original_poem_verse.order
            from data.original_poem_verse
            where original_poem_verse.id in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOccMan(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem_verse.id as verse_id,
                original_poem_verse.idoriginal_poem as occurrence_id,
                document_contains.idcontainer as manuscript_id
            from data.original_poem_verse
            inner join data.document_contains on original_poem_verse.idoriginal_poem = document_contains.idcontent
            where original_poem_verse.id in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getByGroup(int $groupId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem_verse.id as verse_id
            from data.original_poem_verse
            where original_poem_verse.idgroup = ?',
            [$groupId]
        )->fetchAll();
    }

    /**
     * @param  int    $order
     * @param  string $verse
     * @param  int    $occurrenceId
     * @return int    id of the new verse
     */
    public function insert(int $order, string $verse, int $occurrenceId): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.original_poem_verse
                ("order", verse, idoriginal_poem)
                values (?, ?, ?)',
                [
                    $order,
                    $verse,
                    $occurrenceId,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    original_poem_verse.id as verse_id
                from data.original_poem_verse
                order by id desc
                limit 1'
            )->fetch()['verse_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    public function updateOrder(int $id, int $order): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.original_poem_verse
            set "order" = ?
            where id = ?',
            [
                $order,
                $id
            ]
        );
    }

    public function updateVerse(int $id, string $verse): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.original_poem_verse
            set verse = ?
            where id = ?',
            [
                $verse,
                $id
            ]
        );
    }

    public function getGroupId(): int
    {
        $max = $this->conn->executeQuery(
            'SELECT max(idgroup)
            from data.original_poem_verse'
        )->fetch()['max'];
        return $max + 1;
    }

    public function updateGroup(int $id, int $groupId = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.original_poem_verse
            set idgroup = ?
            where id = ?',
            [
                $groupId,
                $id
            ]
        );
    }

    public function delete(int $id): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.original_poem_verse
            where id = ?',
            [$id]
        );
    }
}
