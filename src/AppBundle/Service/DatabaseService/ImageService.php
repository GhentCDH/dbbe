<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class ImageService extends DatabaseService
{
    public function getImagesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                image.idimage as image_id,
                image.filename,
                image.url,
                image.is_private
            from data.image
            where image.idimage in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getIdByFileName(string $filename): array
    {
        return $this->conn->executeQuery(
            'SELECT
                image.idimage as image_id,
                image.filename,
                image.url,
                image.is_private
            from data.image
            where image.filename = ?',
            [$filename]
        )->fetchAll();
    }

    public function insert(string $filename = null, string $url = null, bool $public): int
    {
        $this->beginTransaction();
        try {
            $this->conn->executeUpdate(
                'INSERT INTO data.image (filename, url, is_private)
                values (?, ?, ?)',
                [
                    $filename,
                    $url,
                    $public ? 'FALSE' : 'TRUE',
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    image.idimage as image_id
                from data.image
                order by idimage desc
                limit 1'
            )->fetch()['image_id'];
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    public function updatePublic(int $id, bool $public)
    {
        return $this->conn->executeUpdate(
            'UPDATE data.image
            set is_private = ?
            where idimage = ?',
            [
                $public ? 'FALSE' : 'TRUE',
                $id,
            ]
        );
    }
}
