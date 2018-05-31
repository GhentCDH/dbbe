<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class ImageService extends DatabaseService
{
    public function getByUrl(string $url): array
    {
        return $this->conn->executeQuery(
            'SELECT
                image.idimage as image_id,
                image.url,
                image.is_private
            from data.image
            where image.url = ?',
            [$url]
        )->fetchAll();
    }
}
