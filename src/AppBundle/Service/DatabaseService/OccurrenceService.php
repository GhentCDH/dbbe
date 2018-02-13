<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class OccurrenceService extends DatabaseService
{
    public function getOccurrencesByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                original_poem.identity as occurrence_id,
                document_contains.folium_start,
                document_contains.folium_start_recto,
                document_contains.folium_end,
                document_contains.folium_end_recto,
                document_contains.general_location,
                poem.incipit
            from data.original_poem
            inner join data.document_contains on original_poem.identity = document_contains.idcontent
            inner join data.poem on original_poem.identity = poem.identity
            where original_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
