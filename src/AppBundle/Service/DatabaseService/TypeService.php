<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use AppBundle\Exceptions\DependencyException;

class TypeService extends PoemService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem'
        )->fetchAll();
    }

    public function getNewId(int $oldId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                type_to_reconstructed_poem.identity as new_id
            from migration.type_to_reconstructed_poem
            where type_to_reconstructed_poem.idtype = ?',
            [$oldId]
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.reconstructed_poem on entity.identity = reconstructed_poem.identity'
        )->fetch();
    }

    public function getIdsByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            where reconstructed_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByStatusId(int $statusId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.document_status on reconstructed_poem.identity = document_status.iddocument
            where document_status.idstatus = ?',
            [$statusId]
        )->fetchAll();
    }

    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                typpers.type_id
            from (
                select
                    reconstructed_poem.identity as type_id,
                    bibrole.idperson as person_id
                from data.reconstructed_poem
                inner join data.bibrole on reconstructed_poem.identity = bibrole.iddocument

                union

                select
                    reconstructed_poem.identity as type_id,
                    person.identity as person_id
                from data.reconstructed_poem
                inner join data.factoid on reconstructed_poem.identity = factoid.object_identity
                inner join data.person on factoid.subject_identity = person.identity
            ) as typpers
            where typpers.person_id = ?',
            [$personId]
        )->fetchAll();
    }

    public function getDepIdsByMetreId(int $metreId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.poem_meter on reconstructed_poem.identity = poem_meter.idpoem
            where poem_meter.idmeter = ?',
            [$metreId]
        )->fetchAll();
    }

    public function getDepIdsByGenreId(int $genreId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.document_genre on reconstructed_poem.identity = document_genre.iddocument
            where document_genre.idgenre = ?',
            [$genreId]
        )->fetchAll();
    }

    public function getDepIdsByKeywordId(int $keywordId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                typkey.type_id
            from (
                select
                    reconstructed_poem.identity as type_id,
                    document_keyword.idkeyword as keyword_id
                from data.reconstructed_poem
                inner join data.document_keyword on reconstructed_poem.identity = document_keyword.iddocument

                union

                select
                    reconstructed_poem.identity as type_id,
                    keyword.identity as keyword_id
                from data.reconstructed_poem
                inner join data.factoid on reconstructed_poem.identity = factoid.object_identity
                inner join data.keyword on factoid.subject_identity = keyword.identity
            ) as typkey
            where typkey.keyword_id = ?',
            [$keywordId]
        )->fetchAll();
    }

    public function getDepIdsByAcknowledgementId(int $acknowledgementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.document_acknowledgement on reconstructed_poem.identity = document_acknowledgement.iddocument
            where document_acknowledgement.idacknowledgement = ?',
            [$acknowledgementId]
        )->fetchAll();
    }

    public function getDepIdsByOccurrenceId(int $occurrenceId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.factoid on reconstructed_poem.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.original_poem on factoid.object_identity = original_poem.identity
            where original_poem.identity = ?
            and factoid_type.type = \'based on\'',
            [$occurrenceId]
        )->fetchAll();
    }

    public function getDepIdsByRoleId(int $roleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.bibrole on reconstructed_poem.identity = bibrole.iddocument
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchAll();
    }

    public function getDepIdsByArticleId(int $articleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.reference on reconstructed_poem.identity = reference.idtarget
            inner join data.article on reference.idsource = article.identity
            where article.identity = ?
            UNION
            SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            inner join data.reference on translation_of.idtranslation = reference.idtarget
            inner join data.article on reference.idsource = article.identity
            where article.identity = ?',
            [$articleId, $articleId]
        )->fetchAll();
    }

    public function getDepIdsByBlogPostId(int $blogPostId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.reference on reconstructed_poem.identity = reference.idtarget
            inner join data.blog_post on reference.idsource = blog_post.identity
            where blog_post.identity = ?
            UNION
            SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            inner join data.reference on translation_of.idtranslation = reference.idtarget
            inner join data.blog_post on reference.idsource = blog_post.identity
            where blog_post.identity = ?',
            [$blogPostId, $blogPostId]
        )->fetchAll();
    }

    public function getDepIdsByBookId(int $bookId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.reference on reconstructed_poem.identity = reference.idtarget
            inner join data.book on reference.idsource = book.identity
            where book.identity = ?
            UNION
            SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            inner join data.reference on translation_of.idtranslation = reference.idtarget
            inner join data.book on reference.idsource = book.identity
            where book.identity = ?',
            [$bookId, $bookId]
        )->fetchAll();
    }

    public function getDepIdsByBookChapterId(int $bookChapterId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.reference on reconstructed_poem.identity = reference.idtarget
            inner join data.bookchapter on reference.idsource = bookchapter.identity
            where bookchapter.identity = ?
            UNION
            SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            inner join data.reference on translation_of.idtranslation = reference.idtarget
            inner join data.bookchapter on reference.idsource = bookchapter.identity
            where bookchapter.identity = ?',
            [$bookChapterId, $bookChapterId]
        )->fetchAll();
    }

    public function getDepIdsByOnlineSourceId(int $onlineSourceId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.reference on reconstructed_poem.identity = reference.idtarget
            inner join data.online_source on reference.idsource = online_source.identity
            where online_source.identity = ?
            UNION
            SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            inner join data.reference on translation_of.idtranslation = reference.idtarget
            inner join data.online_source on reference.idsource = online_source.identity
            where online_source.identity = ?',
            [$onlineSourceId, $onlineSourceId]
        )->fetchAll();
    }

    public function getDepIdsByPhdId(int $phdId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.reference on reconstructed_poem.identity = reference.idtarget
            inner join data.phd on reference.idsource = phd.identity
            where phd.identity = ?
            UNION
            SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            inner join data.reference on translation_of.idtranslation = reference.idtarget
            inner join data.phd on reference.idsource = phd.identity
            where phd.identity = ?',
            [$phdId, $phdId]
        )->fetchAll();
    }

    public function getDepIdsByBibVariaId(int $phdId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.reference on reconstructed_poem.identity = reference.idtarget
            inner join data.bib_varia on reference.idsource = bib_varia.identity
            where bib_varia.identity = ?
            UNION
            SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            inner join data.reference on translation_of.idtranslation = reference.idtarget
            inner join data.bib_varia on reference.idsource = bib_varia.identity
            where bib_varia.identity = ?',
            [$phdId, $phdId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.entity_management on reconstructed_poem.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
        )->fetchAll();
    }

    public function getDepIdsByTranslationId(int $translationId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id
            from data.reconstructed_poem
            inner join data.translation_of on reconstructed_poem.identity = translation_of.iddocument
            where translation_of.idtranslation = ?',
            [$translationId]
        )->fetchAll();
    }

    public function getTitles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_title.iddocument as poem_id,
                language.code as lang,
                document_title.title
            from data.document_title
            inner join data.language on document_title.idlanguage = language.idlanguage
            where document_title.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getVerses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document.identity as type_id,
                document.text_content
            from data.document
            where document.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getPrevIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                type_to_reconstructed_poem.identity as document_id,
                type_to_reconstructed_poem.idtype as prev_id
            from migration.type_to_reconstructed_poem
            where type_to_reconstructed_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getKeywords(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_keyword.iddocument as type_id,
                document_keyword.idkeyword as keyword_id
            from data.document_keyword
            where document_keyword.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getStatuses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_status.iddocument as type_id,
                status.idstatus as status_id,
                status.status as status_name,
                status.type as status_type
            from data.document_status
            inner join data.status on document_status.idstatus = status.idstatus
            where document_status.iddocument in (?)
            and status.type in (
                \'type_text\',
                \'type_critical\'
            )',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getOccurrences(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id,
                factoid.subject_identity as occurrence_id
            from data.reconstructed_poem
            inner join data.factoid on reconstructed_poem.identity = factoid.object_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where reconstructed_poem.identity in (?)
            and factoid_type.type = \'reconstruction of\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getRelatedTypes(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                factoid.subject_identity as type_id,
                factoid.object_identity as rel_type_id,
                factoid.idfactoid_type as type_relation_type_id,
                factoid_type.type as name
            from data.factoid
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where factoid.subject_identity in (?)
            and factoid_type.group = \'reconstructed_poem_related_to_reconstructed_poem\'',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getCriticalApparatuses(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                reconstructed_poem.identity as type_id,
                reconstructed_poem.critical_apparatus
            from data.reconstructed_poem
            where reconstructed_poem.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getTranslations(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                translation_of.iddocument as type_id,
                translation_of.idtranslation as translation_id
            from data.translation_of
            where translation_of.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getBasedOns(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                factoid.subject_identity as type_id,
                factoid.object_identity as occurrence_id
            from data.factoid
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where factoid_type.type = \'based on\'
            and factoid.subject_identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function insert(string $incipit): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_reconstructed_poem_has_identity
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.reconstructed_poem default values'
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    reconstructed_poem.identity as type_id
                from data.reconstructed_poem
                order by identity desc
                limit 1'
            )->fetch()['type_id'];
            $this->conn->executeUpdate(
                'UPDATE data.poem
                set incipit = ?
                where identity = ?',
                [
                    $incipit,
                    $id,
                ]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    public function updateVerses(int $id, string $verses): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document
            set text_content = ?
            where document.identity = ?',
            [
                $verses,
                $id,
            ]
        );
    }

    public function delRelatedTypes(int $id, array $relTypeIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.factoid
            using data.factoid_type
            where
            (
                (
                    factoid.subject_identity = ?
                    and factoid.object_identity in (?)
                ) or
                (
                    factoid.subject_identity in (?)
                    and factoid.object_identity = ?
                )
            )
            and factoid_type.group = \'reconstructed_poem_related_to_reconstructed_poem\'',
            [
                $id,
                $relTypeIds,
                $relTypeIds,
                $id,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
                Connection::PARAM_INT_ARRAY,
                \PDO::PARAM_INT,
            ]
        );
    }

    public function delRelatedTypeRelations(int $id, int $relTypeId, array $relationTypeIds): int
    {
        $counter = 0;
        $this->beginTransaction();
        try {
            foreach ($relationTypeIds as $relationTypeId) {
                $counter += $this->conn->executeUpdate(
                    'DELETE from data.factoid
                    using data.factoid_type
                    where
                    (
                        factoid.subject_identity = ?
                        and factoid.object_identity = ?
                        and factoid.idfactoid_type = ?
                    ) or
                    (
                        factoid.subject_identity = ?
                        and factoid.object_identity = ?
                        and factoid.idfactoid_type = coalesce(
                            (select idinverse from data.factoid_type where idfactoid_type = ?),
                            ?
                        )
                    )',
                    [
                        $id,
                        $relTypeId,
                        $relationTypeId,
                        $relTypeId,
                        $id,
                        $relationTypeId,
                        $relationTypeId,
                    ]
                );
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }

        return $counter;
    }

    public function addRelatedType(int $id, int $relTypeId, array $relationTypeIds): int
    {
        $counter = 0;
        $this->beginTransaction();
        try {
            foreach ($relationTypeIds as $relationTypeId) {
                $counter += $this->conn->executeUpdate(
                    'INSERT INTO data.factoid (subject_identity, object_identity, idfactoid_type)
                    values (?, ?, ?)',
                    [
                        $id,
                        $relTypeId,
                        $relationTypeId,
                    ]
                );
                $counter += $this->conn->executeUpdate(
                    'INSERT INTO data.factoid (subject_identity, object_identity, idfactoid_type)
                    values (
                        ?,
                        ?,
                        coalesce((select idinverse from data.factoid_type where idfactoid_type = ?), ?)
                    )',
                    [
                        $relTypeId,
                        $id,
                        $relationTypeId,
                        $relationTypeId,
                    ]
                );
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }

        return $counter;
    }

    /**
     * @param  int $id
     * @param  int $keywordId
     * @return int
     */
    public function addKeyword(int $id, int $keywordId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.document_keyword (iddocument, idkeyword)
            values (?, ?)',
            [
                $id,
                $keywordId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $keywordIds
     * @return int
     */
    public function delKeywords(int $id, array $keywordIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.document_keyword
            where iddocument  = ?
            and idkeyword in (?)',
            [
                $id,
                $keywordIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $criticalApparatus
     * @return int
     */
    public function updateCriticalApparatus(int $id, string $criticalApparatus): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.reconstructed_poem
            set critical_apparatus = ?
            where identity = ?',
            [
                $criticalApparatus,
                $id,
            ]
        );
    }

    public function addTranslation(int $id, string $translation): int
    {
        $counter = 0;
        $this->beginTransaction();
        try {
            $counter += $this->conn->executeUpdate('INSERT INTO data.translation default values');
            $translationId = $this->conn->executeQuery(
                'SELECT
                    translation.identity as translation_id
                from data.translation
                order by identity desc
                limit 1'
            )->fetch()['translation_id'];
            $counter += $this->conn->executeUpdate(
                'INSERT INTO data.document_translation (iddocument, idtranslation) values (?, ?)',
                [
                    $id,
                    $translationId,
                ]
            );
            $counter += $this->conn->executeUpdate(
                'UPDATE data.document
                set text_content = ?
                where identity = ?',
                [
                    $translation,
                    $translationId,
                ]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $counter;
    }

    public function updateTranslation(int $id, string $translation): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document
            set text_content = ?
            from data.translation_of
            where translation_of.idtranslation = document.identity
            and translation_of.iddocument = ?',
            [
                $translation,
                $id,
            ]
        );
    }

    public function delTranslations(int $id): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.translation
            using data.translation_of
            where translation.identity = translation_of.idtranslation
            and translation_of.iddocument = ?',
            [
                $id,
            ]
        );
    }

    public function addBasedOn(int $id, int $basedOnId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.factoid (subject_identity, object_identity, idfactoid_type)
            values (
                ?,
                ?,
                (select idfactoid_type from data.factoid_type where type = \'based on\')
            )',
            [
                $id,
                $basedOnId,
            ]
        );
    }

    public function updateBasedOn(int $id, int $basedOnId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set object_identity = ?
            from data.factoid_type
            where subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type =  \'based on\'',
            [
                $basedOnId,
                $id,
            ]
        );
    }

    public function delBasedOn(int $id): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.factoid
            using data.factoid_type
            where subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type =  \'based on\'',
            [
                $id,
            ]
        );
    }

    public function delete(int $id): int
    {
        $this->beginTransaction();
        try {
            // don't delete if this type is used as reconstruction of
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid.subject_identity = ?
                and factoid_type.type = \'reconstruction of\'',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This type has dependencies.');
            }
            // Set search_path for triggers
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'DELETE from data.factoid
                using factoid_type
                where factoid.subject_identity = ?
                or (
                    factoid.object_identity = ?
                    and factoid.idfactoid_type = factoid_type.idfactoid_type
                    and factoid_type.type = \'subject of\'
                )',
                [
                    $id,
                    $id,
                ]
            );
            // Delete related translations
            $this->conn->executeUpdate(
                'DELETE from data.document
                using translation_of
                where document.identity = translation_of.idtranslation
                and translation_of.iddocument = ?',
                [
                    $id,
                ]
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
