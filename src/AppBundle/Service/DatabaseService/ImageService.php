<?php

namespace AppBundle\Service\DatabaseService;

class ImageService extends DatabaseService
{
    public function getById(int $id): array
    {
        return $this->conn->executeQuery(
            'SELECT
                image.idimage as image_id,
                image.filename,
                image.url,
                image.is_private
            from data.image
            where image.idimage = ?',
            [$id]
        )->fetchAll();
    }
}
