<?php

namespace AppBundle\Service\DatabaseService;

class LanguageService extends DatabaseService
{
    /**
     * @return array
     */
    public function getAllLanguages(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                language.idlanguage as language_id,
                language.name as language_name
            from data.language'
        )->fetchAll();
    }
}
