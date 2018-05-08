<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class KeywordService extends DatabaseService
{
    public function getKeywordsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                keyword.identity as keyword_id,
                keyword.keyword as name
            from data.keyword
            where keyword.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
