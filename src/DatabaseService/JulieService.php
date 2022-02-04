<?php

namespace App\DatabaseService;

class JulieService extends DatabaseService
{
    public function getOriginalPoem(int $id)
    {
        return $this->conn->executeQuery(
            'SELECT
                op.identity as id,
                CASE WHEN EXISTS (SELECT 1 FROM data.entity_management em INNER JOIN data.management m ON em.idmanagement = m.id WHERE identity = ? AND m.name = \'Transcription reviewed\') THEN 1
                     ELSE 0
                END AS transcription_reviewed,
                paleographical_info as "palaeographicalInfo",
                incipit,
                (
                    -- insert carriage return (occurrences were originally entered using Windows PCs) and newline
                    select string_agg(verse, E\'\r\n\')
                    from (select idoriginal_poem, verse from data.original_poem_verse where idoriginal_poem = ? order by "order" ) ordered_verses
                    group by idoriginal_poem
                ) as occurrence_content
            FROM data.original_poem op
            INNER JOIN data.poem p on op.identity = p.identity
            WHERE op.identity = ?',
            [
                $id,
                $id,
                $id,
            ]
        )->fetch();
    }

    public function getSubstringAnnotation(int $occurrenceId)
    {
        return $this->conn->executeQuery(
            'SELECT
                idoccurrence,
                startindex,
                endindex,
                substring,
                idsubstringannotation,
                key,
                value
            FROM julie.substringannotation
            WHERE idoccurrence = ?',
            [$occurrenceId]
        )->fetchAll();
    }

    public function postSubstringAnnotation(int $occurrenceId, $content)
    {
        // Delete previous annotation with the same key, poemid and substring indices
        $this->conn->executeUpdate(
            'DELETE
            FROM julie.substringannotation
            WHERE idoccurrence = ?
            AND startindex = ?
            AND endindex = ?
            AND key = ?',
            [
                $occurrenceId,
                $content['startindex'],
                $content['endindex'],
                $content['key'],
            ]
        );
        // Add new annotation
        return $this->conn->executeUpdate(
            'INSERT INTO julie.substringannotation
            (
                idoccurrence,
                startindex,
                endindex,
                key,
                value,
                substring
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )',
            [
                $occurrenceId,
                $content['startindex'],
                $content['endindex'],
                $content['key'],
                $content['value'],
                $content['substring'],
            ]
        );
    }

    public function deleteSubstringAnnotation(int $annotationId)
    {
        // Delete annotation
        return $this->conn->executeUpdate(
            'DELETE FROM julie.substringannotation
            WHERE idsubstringannotation = ?',
            [
                $annotationId,
            ]
        );
    }

    public function getPoemAnnotation(int $id)
    {
        return $this->conn->executeQuery(
            'SELECT
                idoccurrence,
                prosodycorrect
            FROM julie.poemannotation
            WHERE idoccurrence = ?',
            [$id]
        )->fetch();
    }

    public function upsertPoemAnnotationProsodyCorrect(int $occurrenceId, string $prosodyCorrect = null)
    {
        return $this->conn->executeUpdate(
            'INSERT INTO julie.poemannotation
            (
                idoccurrence,
                prosodycorrect
            ) VALUES (
                ?,
                ?
            )
            ON CONFLICT (idoccurrence)
            DO UPDATE SET prosodycorrect = ?',
            [
                $occurrenceId,
                $prosodyCorrect,
                $prosodyCorrect,
            ]
        );
    }
}