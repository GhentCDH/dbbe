<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class TranslationService extends DocumentService
{
    /**
     * @param  array $ids
     * @return array
     */
    public function getTranslationsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                translation.identity as translation_id,
                language.idlanguage as language_id,
                language.name as language_name,
                document.text_content
            from data.translation
            inner join data.language on translation.idlanguage = language.idlanguage
            inner join data.document on translation.identity = document.identity
            where translation.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByBookId(int $bookId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                translation.identity as translation_id
            from data.translation
            inner join data.reference on translation.identity = reference.idtarget
            inner join data.book on reference.idsource = book.identity
            where book.identity = ?',
            [$bookId]
        )->fetchAll();
    }

    public function insert(int $documentId, int $languageId, string $text): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_translation_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.translation (idlanguage)
                values (?)',
                [
                    $languageId,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    translation.identity as translation_id
                from data.translation
                order by identity desc
                limit 1'
            )->fetch()['translation_id'];
            $this->conn->executeUpdate(
                'INSERT INTO data.translation_of (iddocument, idtranslation)
                values (?, ?)',
                [
                    $documentId,
                    $id,
                ]
            );
            $this->updateText($id, $text);
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    public function updateText(int $id, string $text)
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document
            set text_content = ?
            where document.identity = ?',
            [
                $text,
                $id,
            ]
        );
    }

    public function updateLanguage(int $id, int $languageId)
    {
        return $this->conn->executeUpdate(
            'UPDATE data.translation
            set idlanguage = ?
            where translation.identity = ?',
            [
                $languageId,
                $id,
            ]
        );
    }

    /**
     * Delete translation
     * Cascade to translation_of and document tables
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for triggers
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'DELETE from data.translation
                where translation.identity = ?',
                [$id]
            );
            $delete = $this->conn->executeUpdate(
                'DELETE from data.document
                where document.identity = ?',
                [$id]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $delete;
    }
}
