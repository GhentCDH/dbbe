<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class BibliographyService extends DatabaseService
{
    public function getBibliographiesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reference.idreference as reference_id,
                reference.idsource as source_id,
                reference.page_start,
                reference.page_end,
                case when reference.page_start is null then reference.temp_page_removeme else null end as raw_pages,
                reference.url as rel_url,
                reference_type.type
            from data.reference
            left join data.reference_type on reference.idreference_type = reference_type.idreference_type
            where reference.idreference in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function addBibliography(
        int $targetId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $url = null
    ): int {
        return $this->conn->executeUpdate(
            'INSERT INTO data.reference (idtarget, idsource, page_start, page_end, url)
            values (?, ?, ?, ?, ?)',
            [
                $targetId,
                $sourceId,
                $startPage,
                $endPage,
                $url,
            ]
        );
    }

    public function updateBibliography(
        int $referenceId,
        int $sourceId,
        string $startPage = null,
        string $endPage = null,
        string $rawPages = null,
        string $url = null
    ): int {
        return $this->conn->executeUpdate(
            'UPDATE data.reference
            set idsource = ?, page_start = ?, page_end = ?, temp_page_removeme = ?, url = ?
            where idreference = ?',
            [
                $sourceId,
                $startPage,
                $endPage,
                $rawPages,
                $url,
                $referenceId,
            ]
        );
    }

    public function delBibliographies(array $ids): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.reference
            where reference.idreference in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }
}
