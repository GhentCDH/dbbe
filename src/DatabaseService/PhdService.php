<?php

namespace App\DatabaseService;

use Exception;

use App\Exceptions\DependencyException;

use Doctrine\DBAL\Connection;

class PhdService extends DocumentService
{
    /**
     * Get all PhD thesis ids
     * @return array
     */
    public function getIds(): array
    {
        return $this->conn->query(
            'SELECT
                phd.identity as phd_id
            from data.phd'
        )->fetchAll();
    }

    public function getLastModified(): array
    {
        return $this->conn->executeQuery(
            'SELECT
                max(modified) as modified
            from data.entity
            inner join data.phd on entity.identity = phd.identity'
        )->fetch();
    }

    /**
     * Get all ids of PhD theses that are dependent on a specific person
     * @param  int   $personId
     * @return array
     */
    public function getDepIdsByPersonId(int $personId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                phd.identity as phd_id
            from data.phd
            inner join data.bibrole on phd.identity = bibrole.iddocument
            where bibrole.idperson = ?',
            [$personId]
        )->fetchAll();
    }

    /**
     * Get all ids of PhD theses that are dependent on specific references
     * @param  array $referenceIds
     * @return array
     */
    public function getDepIdsByReferenceIds(array $referenceIds): array
    {
        return $this->conn->executeQuery(
            'SELECT
                phd.identity as phd_id
            from data.phd
            inner join data.reference on phd.identity = reference.idsource
            where reference.idreference in (?)',
            [$referenceIds],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getDepIdsByRoleId(int $roleId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                phd.identity as phd_id
            from data.phd
            inner join data.bibrole on phd.identity = bibrole.iddocument
            where bibrole.idrole = ?',
            [$roleId]
        )->fetchAll();
    }

    public function getDepIdsByManagementId(int $managementId): array
    {
        return $this->conn->executeQuery(
            'SELECT
                phd.identity as phd_id
            from data.phd
            inner join data.entity_management on phd.identity = entity_management.identity
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
                phd.identity as phd_id,
                document_title.title,
                phd.year,
                phd.forthcoming,
                phd.city,
                phd.institution,
                phd.volume
            from data.phd
            left join data.document_title on phd.identity = document_title.iddocument
            where phd.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    /**
     * @param  string   $title
     * @param  int|null $year
     * @param  bool     $forthcoming
     * @param  string   $city
     * @return int
     */
    public function insert(string $title, int $year = null, bool $forthcoming, string $city): int
    {
        $this->beginTransaction();
        try {
            // Set search_path for trigger ensure_phd_has_document
            $this->conn->exec('SET SEARCH_PATH TO data');
            $this->conn->executeUpdate(
                'INSERT INTO data.phd (year, forthcoming, city)
                values (?, ?, ?)',
                [
                    $year,
                    $forthcoming ? 't' : 'f',
                    $city,
                ]
            );
            $id = $this->conn->executeQuery(
                'SELECT
                    phd.identity as phd_id
                from data.phd
                order by identity desc
                limit 1'
            )->fetch()['phd_id'];
            if ($title != null) {
                $this->conn->executeQuery(
                    'INSERT INTO data.document_title (iddocument, idlanguage, title)
                    values (?, (select idlanguage from data.language where name = \'Unknown\'), ?)',
                    [
                        $id,
                        $title,
                    ]
                );
            }
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
            'UPDATE data.phd
            set year = ?
            where phd.identity = ?',
            [
                $year,
                $id,
            ]
        );
    }

    /**
     * @param  int  $id
     * @param  bool $forthcoming
     * @return int
     */
    public function updateForthcoming(int $id, bool $forthcoming): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.phd
            set forthcoming = ?
            where phd.identity = ?',
            [
                $forthcoming ? 't' : 'f',
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $city
     * @return int
     */
    public function updateCity(int $id, string $city): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.phd
            set city = ?
            where phd.identity = ?',
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
            'UPDATE data.phd
            set institution = ?
            where phd.identity = ?',
            [
                $institution,
                $id,
            ]
        );
    }

    /**
     * @param  int         $id
     * @param  string|null $volume
     * @return int
     */
    public function updateVolume(int $id, string $volume = null): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.phd
            set volume = ?
            where phd.identity = ?',
            [
                $volume,
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
            // don't delete if this PhD thesis is used in reference
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.reference
                where reference.idsource = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This PhD thesis has reference dependencies.');
            }
            // don't delete if this PhD thesis is used in global_id
            $count = $this->conn->executeQuery(
                'SELECT count(*)
                from data.global_id
                where global_id.idauthority = ?',
                [$id]
            )->fetchOne(0);
            if ($count > 0) {
                throw new DependencyException('This PhD thesis has global_id dependencies.');
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
