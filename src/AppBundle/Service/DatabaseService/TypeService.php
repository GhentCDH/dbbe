<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class TypeService extends DocumentService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem'
        )->fetchAll();
    }

    public function getIncipits(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id,
                poem.incipit
            from data.reconstructed_poem
            inner join data.poem on reconstructed_poem.identity = poem.identity
            where reconstructed_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
