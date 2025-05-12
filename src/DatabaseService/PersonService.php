<?php

namespace App\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

use App\Exceptions\DependencyException;

class PersonService extends EntityService
{
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                person.identity as person_id
            from data.person'
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.person on entity.identity = person.identity'
        )->fetch();
    }

    public function getHistoricalIds(): array
    {
        return $this->conn->query(
            'SELECT
                person.identity as person_id
            from data.person
            where person.is_historical = TRUE'
        )->fetchAll();
    }

    public function getModernIds(): array
    {
        return $this->conn->query(
            'SELECT
                person.identity as person_id
            from data.person
            where person.is_modern = TRUE'
        )->fetchAll();
    }

    public function getDBBEIds(): array
    {
        return $this->conn->query(
            'SELECT
                person.identity as person_id
            from data.person
            where person.is_dbbe= TRUE'
        )->fetchAll();
    }

    public function getDepIdsByOfficeId(int $officeId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_occupation.idperson as person_id
            from data.person_occupation
            where person_occupation.idoccupation = ?',
            [$officeId]
        )->fetchAll();
    }

    public function getDepIdsByOfficeIdWithChildren(int $officeId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_occupation.idperson as person_id
            from data.person_occupation
            where person_occupation.idoccupation in (
                WITH RECURSIVE rec (id, idparent) AS (
                    SELECT
                        o.idoccupation,
                        o.idparentoccupation
                    FROM data.occupation as o

                    UNION ALL

                    SELECT
                        rec.id,
                        o.idparentoccupation
                    FROM rec
                    INNER JOIN data.occupation o
                    ON o.idoccupation = rec.idparent
                )
                SELECT id
                FROM rec
                WHERE rec.idparent = ? or rec.id = ?
            )',
            [
                $officeId,
                $officeId,
            ]
        )->fetchAll();
    }

    public function getDepIdsByRegionId(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.factoid on person.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.location on factoid.idlocation = location.idlocation
            where factoid_type.type =  \'origination\'
            and location.idregion = ?',
            [$regionId]
        )->fetchAll();
    }

    public function getDepIdsByRegionIdWithChildren(int $regionId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.factoid on person.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.location on factoid.idlocation = location.idlocation
            where factoid_type.type =  \'origination\'
            and location.idregion in (
                WITH RECURSIVE rec (id, idparent) AS (
                    SELECT
                        r.identity,
                        r.parent_idregion
                    FROM data.region r

                    UNION ALL

                    SELECT
                        rec.id,
                        r.parent_idregion
                    FROM rec
                    INNER JOIN data.region r
                    ON r.identity = rec.idparent
                )
                SELECT id
                FROM rec
                WHERE rec.idparent = ? or rec.id = ?
            )',
            [
                $regionId,
                $regionId,
            ]
        )->fetchAll();
    }

    public function getDepIdsBySelfDesignationId(int $selfDesignationId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_self_designation.idperson as person_id
            from data.person_self_designation
            where person_self_designation.idself_designation = ?',
            [$selfDesignationId]
        )->fetchAll();
    }

    public function getDepIdsByManuscriptId(int $manuscriptId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.bibrole on person.identity = bibrole.idperson
            inner join data.manuscript on bibrole.iddocument = manuscript.identity
            where manuscript.identity = ?',
            [$manuscriptId]
        )->fetchAll();
    }

    public function getDepIdsByOccurrenceId(int $occurrenceId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.bibrole on person.identity = bibrole.idperson
            inner join data.original_poem on bibrole.iddocument = original_poem.identity
            where original_poem.identity = ?',
            [$occurrenceId]
        )->fetchAll();
    }

    public function getDepIdsByTypeId(int $typeId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.bibrole on person.identity = bibrole.idperson
            inner join data.reconstructed_poem on bibrole.iddocument = reconstructed_poem.identity
            where reconstructed_poem.identity = ?',
            [$typeId]
        )->fetchAll();
    }

    public function getDepIdsByArticleId(int $articleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.reference on person.identity = reference.idtarget
            inner join data.article on reference.idsource = article.identity
            where article.identity = ?',
            [$articleId]
        )->fetchAll();
    }

    public function getDepIdsByBlogPostId(int $blogPostId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.reference on person.identity = reference.idtarget
            inner join data.blog_post on reference.idsource = blog_post.identity
            where blog_post.identity = ?',
            [$blogPostId]
        )->fetchAll();
    }

    public function getDepIdsByBookId(int $bookId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.reference on person.identity = reference.idtarget
            inner join data.book on reference.idsource = book.identity
            where book.identity = ?',
            [$bookId]
        )->fetchAll();
    }

    public function getDepIdsByBookChapterId(int $bookChapterId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.reference on person.identity = reference.idtarget
            inner join data.bookchapter on reference.idsource = bookchapter.identity
            where bookchapter.identity = ?',
            [$bookChapterId]
        )->fetchAll();
    }

    public function getDepIdsByOnlineSourceId(int $onlineSourceId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.reference on person.identity = reference.idtarget
            inner join data.online_source on reference.idsource = online_source.identity
            where online_source.identity = ?',
            [$onlineSourceId]
        )->fetchAll();
    }

    public function getDepIdsByPhdId(int $phdId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.reference on person.identity = reference.idtarget
            inner join data.phd on reference.idsource = phd.identity
            where phd.identity = ?',
            [$phdId]
        )->fetchAll();
    }

    public function getDepIdsByBibVariaId(int $phdId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.reference on person.identity = reference.idtarget
            inner join data.bib_varia on reference.idsource = bib_varia.identity
            where bib_varia.identity = ?',
            [$phdId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id
            from data.person
            inner join data.entity_management on person.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
        )->fetchAll();
    }

    public function getBasicInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id,
                name.first_name,
                name.last_name,
                factoid_origination.location_id,
                name.extra,
                name.unprocessed,
                person.is_historical,
                person.is_modern,
                person.is_dbbe,
                factoid_born.born_date,
                factoid_died.death_date,
                factoid_attested.attested_dates,
                factoid_attested.attested_intervals
            from data.person
            inner join data.name on name.idperson = person.identity
            left join (
                select
                    factoid.subject_identity,
                    factoid.date as born_date
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'born\'
            ) as factoid_born on person.identity = factoid_born.subject_identity
            left join (
                select
                    factoid.subject_identity,
                    factoid.date as death_date
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'died\'
            ) as factoid_died on person.identity = factoid_died.subject_identity
            left join (
                select
                    factoid.subject_identity,
                    array_to_json(array_agg(factoid.date)) as attested_dates,
                    array_to_json(array_agg(factoid.interval)) as attested_intervals
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'attested\'
                group by factoid.subject_identity
            ) as factoid_attested on person.identity = factoid_attested.subject_identity
            left join (
                select
                    factoid.subject_identity,
                    factoid.idlocation as location_id
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'origination\'
            ) as factoid_origination on person.identity = factoid_origination.subject_identity
            where person.identity in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getSelfDesignations(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person.identity as person_id,
                self_designation.id as self_designation_id,
                self_designation.name
                from data.person
                inner join data.person_self_designation on person.identity = person_self_designation.idperson
                inner join data.self_designation on person_self_designation.idself_designation = self_designation.id
                where person.identity in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as document_id,
                bibrole.idrole as role_id,
                case
                    when manuscript.identity is not null then \'manuscript\'
                    when original_poem.identity is not null then \'occurrence\'
                    when reconstructed_poem.identity is not null then \'type\'
                end as document_key
            from data.bibrole
            left join data.manuscript on bibrole.iddocument = manuscript.identity
            left join data.original_poem on bibrole.iddocument = original_poem.identity
            left join data.reconstructed_poem on bibrole.iddocument = reconstructed_poem.identity
            where bibrole.idperson in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }


    public function getOffices(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_occupation.idperson as person_id,
                person_occupation.idoccupation as office_id
            from data.person_occupation
            where person_occupation.idperson in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getManuscriptsAsRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT -- bibrole of manuscript
                bibrole.idperson as person_id,
                bibrole.iddocument as manuscript_id,
                null as occurrence_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.manuscript on bibrole.iddocument = manuscript.identity
            inner join data.role on bibrole.idrole = role.idrole
            where bibrole.idperson in (?)

            union all

            SELECT -- bibrole of occurrence in manuscript
                bibrole.idperson as person_id,
                document_contains.idcontainer as manuscript_id,
                bibrole.iddocument as occurrence_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.document_contains on bibrole.iddocument = document_contains.idcontent
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            inner join data.role on bibrole.idrole = role.idrole
            where bibrole.idperson in (?)
            and (role.is_contributor_role is null or role.is_contributor_role = false)',
            [
                $ids,
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getOccurrencesAsRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as occurrence_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.original_poem on bibrole.iddocument = original_poem.identity
            inner join data.role on bibrole.idrole = role.idrole
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getOccurrencesAsSubjects(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                factoid.subject_identity as person_id,
                factoid.object_identity as occurrence_id
            from data.factoid
            inner join data.original_poem on factoid.object_identity = original_poem.identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where factoid.subject_identity in (?)
            and factoid_type.type = \'subject of\'',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getTypesAsRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as type_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.reconstructed_poem on bibrole.iddocument = reconstructed_poem.identity
            inner join data.role on bibrole.idrole = role.idrole
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getTypesAsSubjects(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                factoid.subject_identity as person_id,
                factoid.object_identity as type_id
            from data.factoid
            inner join data.reconstructed_poem on factoid.object_identity = reconstructed_poem.identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            where factoid.subject_identity in (?)
            and factoid_type.type = \'subject of\'',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getArticles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as article_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.article on bibrole.iddocument = article.identity
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getBooks(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as book_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.book on bibrole.iddocument = book.identity
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getBookChapters(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as book_chapter_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.bookchapter on bibrole.iddocument = bookchapter.identity
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getBlogPosts(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as blog_post_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.blog_post on bibrole.iddocument = blog_post.identity
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getPhds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as phd_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.phd on bibrole.iddocument = phd.identity
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getBibVarias(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.idperson as person_id,
                bibrole.iddocument as bib_varia_id,
                bibrole.idrole as role_id
            from data.bibrole
            inner join data.bib_varia on bibrole.iddocument = bib_varia.identity
            where bibrole.idperson in (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getManuscriptsAsContents(array $ids): array
    {
        // Get all manuscript ids with a person as content (or any of the children of this content)
        return $this->conn->executeQuery(
            'WITH RECURSIVE rec (id, idparent, idperson) AS (
                SELECT
                g.idgenre,
                g.idparentgenre,
                g.idperson
                FROM data.genre g

                UNION ALL

                SELECT
                rec.id,
                g.idparentgenre,
                g.idperson
                FROM rec
                INNER JOIN data.genre g
                ON g.idgenre = rec.idparent
            )
            SELECT manuscript.identity as manuscript_id,
            rec.idperson as person_id
            FROM data.manuscript
            INNER JOIN data.document_genre ON manuscript.identity = document_genre.iddocument
            INNER JOIN rec on document_genre.idgenre = rec.id
            WHERE rec.idperson IN (?)',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function insert(): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_person_has_identity
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.person default values'
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    person.identity as person_id
                from data.person
                order by identity desc
                limit 1'
            )->fetch()['person_id'];
            $this->conn->executeUpdate(
                'INSERT INTO data.name (idperson) values (?)',
                [
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

    public function updateFirstName(int $id, string $firstName = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.name
            set first_name = ?
            where name.idperson = ?',
            [
                $firstName,
                $id,
            ]
        );
    }

    public function updateLastName(int $id, string $lastName = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.name
            set last_name = ?
            where name.idperson = ?',
            [
                $lastName,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $selfDesignationId
     * @return int
     */
    public function addSelfDesignation(int $id, int $selfDesignationId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.person_self_designation (idperson, idself_designation)
            values (?, ?)',
            [
                $id,
                $selfDesignationId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $selfDesignationIds
     * @return int
     */
    public function delSelfDesignations(int $id, array $selfDesignationIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.person_self_designation
            where idperson = ?
            and idself_designation in (?)',
            [
                $id,
                $selfDesignationIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function insertOrigin(int $id, int $locationId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.factoid (subject_identity, idlocation, idfactoid_type)
            values (
                ?,
                ?,
                (select factoid_type.idfactoid_type from data.factoid_type where factoid_type.type = \'origination\')
            )',
            [
                $id,
                $locationId,
            ]
        );
    }

    public function updateOrigin(int $id, int $locationId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set idlocation = ?
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = \'origination\'',
            [
                $locationId,
                $id,
            ]
        );
    }

    public function deleteOrigin(int $id): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.factoid
            using data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = \'origination\'',
            [
                $id,
            ]
        );
    }

    /**
     * Helper for regionmanager -> merge
     * @param  int $id
     * @param  int $regionId
     * @return int
     */
    public function updateRegion(int $id, int $regionId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.factoid
            set idlocation = (select idlocation from data.location where idregion = ?)
            from data.factoid_type
            where factoid.subject_identity = ?
            and factoid.idfactoid_type = factoid_type.idfactoid_type
            and factoid_type.type = \'origination\'',
            [
                $regionId,
                $id,
            ]
        );
    }

    public function updateExtra(int $id, string $extra = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.name
            set extra = ?
            where name.idperson = ?',
            [
                $extra,
                $id,
            ]
        );
    }

    public function updateUnprocessed(int $id, string $unprocessed = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.name
            set unprocessed = ?
            where name.idperson = ?',
            [
                $unprocessed,
                $id,
            ]
        );
    }

    public function updateHistorical(int $id, bool $historical): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.person
            set is_historical = ?
            where person.identity = ?',
            [
                $historical ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    public function updateModern(int $id, bool $modern): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.person
            set is_modern = ?
            where person.identity = ?',
            [
                $modern ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    public function updateDBBE(int $id, bool $dbbe): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.person
            set is_dbbe = ?
            where person.identity = ?',
            [
                $dbbe ? 'TRUE': 'FALSE',
                $id,
            ]
        );
    }

    public function delOffices(int $id, array $officeIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.person_occupation
            where person_occupation.idperson = ?
            and person_occupation.idoccupation in (?)',
            [
                $id,
                $officeIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function addOffice(int $id, int $officeId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.person_occupation (idperson, idoccupation)
            values (?, ?)',
            [
                $id,
                $officeId,
            ]
        );
    }

    public function mergePoemBibroles(int $primaryId, int $secondaryId): int
    {
        $numberOfRows = $this->conn->executeUpdate(
            'UPDATE data.bibrole
            set idperson = ?
            from data.poem
            where bibrole.idperson = ? and bibrole.iddocument = poem.identity',
            [
                $primaryId,
                $secondaryId,
            ]
        );

        // Remove duplicates
        $this->conn->executeUpdate(
            'delete from data.bibrole a using (
                select min(ctid) as ctid, idperson, iddocument, idrole
                from data.bibrole
                group by idperson, iddocument, idrole
                having count(*) > 1
            ) b
            where a.idperson = b.idperson and a.iddocument = b.iddocument and a.idrole = b.idrole and a.ctid <> b.ctid'
        );

        return $numberOfRows;
    }

    public function mergePoemSubjects(int $primaryId, int $secondaryId): int
    {
        $numberOfRows = $this->conn->executeUpdate(
        'UPDATE data.factoid
                set subject_identity = ?
                from data.factoid_type
                where factoid.subject_identity = ?
                and factoid.idfactoid_type = factoid_type.idfactoid_type
                and factoid_type.type = \'subject of\'',
            [
                $primaryId,
                $secondaryId,
            ]
        );

        // Remove duplicates
        $this->conn->executeUpdate(
            'delete from data.factoid a using (
                select min(idfactoid) as idfactoid, subject_identity, object_identity, date, interval, idlocation, idfactoid_type
                from data.factoid
                group by subject_identity, object_identity, date, interval, idlocation, idfactoid_type
                having count(*) > 1
            ) b
            where a.subject_identity = b.subject_identity
            and a.object_identity = b.object_identity
            and a.date = b.date
            and a.interval = b.interval
            and a.idlocation = b.idlocation
            and a.idfactoid_type = b.idfactoid_type
            and a.idfactoid <> b.idfactoid'
        );

        return $numberOfRows;
    }

    public function delete(int $id): int
    {
        $this->beginTransaction();
        try {
            // don't delete if this person is used in bibrole
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.bibrole
                where bibrole.idperson = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This person has bibrole dependencies.');
            }
            // don't delete if this person is used in factoid
            // as object
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.factoid
                where factoid.object_identity = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This person has factoid dependencies.');
            }
            // as subject with type subject of
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.factoid
                inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
                where factoid_type.type = \'subject of\'
                and factoid.subject_identity = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This person has factoid dependencies.');
            }
            // don't delete if this person is used in global_id
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.global_id
                where global_id.idauthority = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This person has global_id dependencies.');
            }
            // don't delete if this person is used in content
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.genre
                where genre.idperson = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This person has content dependencies.');
            }
            // Set search_path for triggers
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'DELETE from data.factoid
                where factoid.subject_identity = ?',
                [$id]
            );
            $delete = $this->conn->executeUpdate(
                'DELETE from data.person
                where person.identity = ?',
                [$id]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $delete;
    }

    /**
     * @param  int $id
     * @param  int $acknowledgementId
     * @return int
     */
    public function addAcknowledgement(int $id, int $acknowledgementId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.person_acknowledgement (idperson, idacknowledgement)
            values (?, ?)',
            [
                $id,
                $acknowledgementId,
            ]
        );
    }

    /**
     * @param  int   $id
     * @param  array $acknowledgementIds
     * @return int
     */
    public function delAcknowledgements(int $id, array $acknowledgementIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.person_acknowledgement
            where idperson = ?
            and idacknowledgement in (?)',
            [
                $id,
                $acknowledgementIds,
            ],
            [
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function getAcknowledgements(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                person_acknowledgement.idperson as person_id,
                person_acknowledgement.idacknowledgement as acknowledgement_id,
                acknowledgement.acknowledgement as name
            from data.person_acknowledgement
            inner join data.acknowledgement on person_acknowledgement.idacknowledgement = acknowledgement.id
            where person_acknowledgement.idperson in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }
}
