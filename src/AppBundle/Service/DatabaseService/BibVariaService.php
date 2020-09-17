<?php

namespace AppBundle\Service\DatabaseService;

use Exception;

use AppBundle\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class BibVariaService extends DocumentService
{
    /**
     * Get all bib varia ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                bib_varia.identity as bib_varia_id
            from data.bib_varia'
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.bib_varia on entity.identity = bib_varia.identity'
        )->fetch();
    }

    /**
     * Get all ids of bib varias that are dependent on a specific person
     * @param  int   $personId
     * @return array
     */
    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bib_varia.identity as bib_varia_id
            from data.bib_varia
            inner join data.bibrole on bib_varia.identity = bibrole.iddocument
            where bibrole.idperson = ?',
            [$personId]
        )->fetchAll();
    }

    /**
     * Get all ids of bib varias that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getDepIdsByReferenceIds(array $referenceIds): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bib_varia.identity as bib_varia_id
            from data.bib_varia
            inner join data.reference on bib_varia.identity = reference.idsource
            where reference.idreference in (?)',
            [$referenceIds],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByRoleId(int $roleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bib_varia.identity as bib_varia_id
            from data.bib_varia
            inner join data.bibrole on bib_varia.identity = bibrole.iddocument
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bib_varia.identity as bib_varia_id
            from data.bib_varia
            inner join data.entity_management on bib_varia.identity = entity_management.identity
            where entity_management.idmanagement = ?',
            [$managementId]
        )->fetchAll();
    }

    /**
     * @param  array $ids
     * @return array
     */
    public function getMiniInfoByIds(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bib_varia.identity as bib_varia_id,
                document_title.title,
                bib_varia.year,
                bib_varia.city,
                bib_varia.institution
            from data.bib_varia
            inner join data.document_title on bib_varia.identity = document_title.iddocument
            where bib_varia.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string      $title
     * @param  int|null    $year
     * @param  string|null $city
     * @return int
     */
    public function insert(string $title, int $year = null, string $city = null): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_book_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.bib_varia (year, city)
                values (?, ?)',
                [
                    $year,
                    $city,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    bib_varia.identity as bib_varia_id
                from data.bib_varia
                order by identity desc
                limit 1'
            )->fetch()['bib_varia_id'];
            $this->conn->executeQuery(
                'INSERT INTO data.document_title (iddocument, idlanguage, title)
                values (?, (select idlanguage from data.language where name = \'Unknown\'), ?)',
                [
                    $id,
                    $title,
                ]
            );
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * @param  int      $id
     * @param  int|null $year
     * @return int
     */
    public function updateYear(int $id, int $year = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.bib_varia
            set year = ?
            where bib_varia.identity = ?',
            [
                $year,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $city
     * @return int
     */
    public function updateCity(int $id, string $city = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.bib_varia
            set city = ?
            where bib_varia.identity = ?',
            [
                $city,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $institution
     * @return int
     */
    public function updateInstitution(int $id, string $institution = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.bib_varia
            set institution = ?
            where bib_varia.identity = ?',
            [
                $institution,
                $id,
            ]
        );
    }

    /**
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        $this->beginTransaction();
        try {
            // don't delete if this bib varia is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchColumn(0);
            if ($count > 0) {
                throw new DependencyException('This bib varia has dependencies.');
            }
            // Set search_path for triggers
            $this->conn->exec('SET SEARCH_PATH TO data');
            $delete = $this->conn->executeUpdate(
                'DELETE from data.entity
                where entity.identity = ?',
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
