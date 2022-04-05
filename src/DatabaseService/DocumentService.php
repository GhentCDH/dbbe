<?php

namespace App\DatabaseService;

use Exception;

use Doctrine\DBAL\Connection;

class DocumentService extends EntityService
{

    /**
     * Get all ids of documents that are dependent on a specific person
     * provide a dummy function that returns an empty list
     * @param  int   $personId
     * @return array empty array
     */
    public function getDepIdsByPersonId(int $personId): array
    {
        return [];
    }

    public function getCompletionDates(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document.identity as document_id,
                factoid_merge.factoid_date as completion_date
            from data.document
            inner join (
                select
                    factoid.subject_identity as factoid_identity,
                    factoid.date as factoid_date
                from data.factoid
                inner join data.factoid_type
                    on factoid.idfactoid_type = factoid_type.idfactoid_type
                        and factoid_type.type = \'completed at\'
            ) factoid_merge on document.identity = factoid_merge.factoid_identity
            where document.identity in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function getPersonRoles(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                bibrole.iddocument as document_id,
                bibrole.idperson as person_id,
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name,
                role.is_contributor_role as role_is_contributor_role,
                role.has_rank as role_has_rank,
                role.order as role_order
            from data.bibrole
            inner join data.role on bibrole.idrole = role.idrole
            where (role.is_contributor_role is null or role.is_contributor_role = false)
            and bibrole.iddocument in (?)
            order by bibrole.rank',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getContributorRoles(array $ids): array
    {
        // Sort contributor roles in the order in which they were added (https://github.ugent.be/idevos/DBBE-workflow/issues/453#issuecomment-125543).
        return $this->conn->executeQuery(
            'SELECT
                bibrole.iddocument as document_id,
                bibrole.idperson as person_id,
                role.idrole as role_id,
                array_to_json(role.type) as role_usage,
                role.system_name as role_system_name,
                role.name as role_name,
                role.is_contributor_role as role_is_contributor_role,
                role.has_rank as role_has_rank,
                role.order as role_order
            from data.bibrole
            inner join data.role on bibrole.idrole = role.idrole
            where role.is_contributor_role = true
            and bibrole.iddocument in (?)
            order by bibrole.rank, bibrole.created',
            [
                $ids,
            ],
            [
                Connection::PARAM_INT_ARRAY,
            ]
        )->fetchAll();
    }

    public function getAcknowledgements(array $ids): array
    {
        return $this->conn->executeQuery(
            'SELECT
                document_acknowledgement.iddocument as document_id,
                document_acknowledgement.idacknowledgement as acknowledgement_id,
                acknowledgement.acknowledgement as name
            from data.document_acknowledgement
            inner join data.acknowledgement on document_acknowledgement.idacknowledgement = acknowledgement.id
            where document_acknowledgement.iddocument in (?)',
            [$ids],
            [Connection::PARAM_INT_ARRAY]
        )->fetchAll();
    }

    public function addPersonRole(int $documentId, int $roleId, int $personId): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO data.bibrole (iddocument, idrole, idperson)
            values (
                ?,
                ?,
                ?
            )',
            [
                $documentId,
                $roleId,
                $personId,
            ]
        );
    }

    public function delPersonRole(int $documentId, int $roleId, array $personIds): int
    {
        return $this->conn->executeUpdate(
            'DELETE
            from data.bibrole
            where bibrole.iddocument = ?
            and bibrole.idrole = ?
            and bibrole.idperson in (?)',
            [
                $documentId,
                $roleId,
                $personIds,
            ],
            [
                \PDO::PARAM_INT,
                \PDO::PARAM_INT,
                Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    public function updatePersonRoleRank(int $documentId, int $personId, int $rank): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.bibrole
            set rank = ?
            where bibrole.iddocument = ?
            and bibrole.idperson = ?',
            [
                $rank,
                $documentId,
                $personId
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  string $title
     * @return int
     */
    public function updateTitle(int $id, string $title): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.document_title
            set title = ?
            where document_title.iddocument = ?',
            [
                $title,
                $id,
            ]
        );
    }

    /**
     * @param  int    $id
     * @param  int    $statusId
     * @param  string $statusType
     * @return int
     */
    public function upsertStatus(int $id, int $statusId, string $statusType): int
    {
        $this->beginTransaction();
        try {
            $upsert = $this->conn->executeUpdate(
                'UPDATE data.document_status
                set idstatus = ?
                from data.status
                where iddocument = ?
                and document_status.idstatus = status.idstatus
                and status.type = ?',
                [
                    $statusId,
                    $id,
                    $statusType,
                ]
            );
            if (!$upsert) {
                $upsert = $this->conn->executeUpdate(
                    'INSERT into data.document_status (iddocument, idstatus)
                    values (?, ?)',
                    [
                        $id,
                        $statusId,
                    ]
                );
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
        return $upsert;
    }

    /**
     * @param  int    $id
     * @param  string $statusType
     * @return int
     */
    public function deleteStatus(int $id, string $statusType): int
    {
        return $this->conn->executeUpdate(
            'DELETE from data.document_status
            using data.status
            where iddocument = ?
            and document_status.idstatus = status.idstatus
            and status.type = ?',
            [
                $id,
                $statusType,
            ]
        );
    }

    /**
     * @param  int $id
     * @param  int $acknowledgementId
     * @return int
     */
    public function addAcknowledgement(int $id, int $acknowledgementId): int
    {
        return $this->conn->executeUpdate(
            'INSERT into data.document_acknowledgement (iddocument, idacknowledgement)
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
            from data.document_acknowledgement
            where iddocument = ?
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
}
