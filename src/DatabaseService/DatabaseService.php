<?php

namespace App\DatabaseService;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The DatabaseService is the parent database service class.
 * It provides common functions that can be reused by its child classes.
 */
class DatabaseService implements DatabaseServiceInterface
{
    /**
     * The connection to the database.
     * @var \Doctrine\DBAL\Connection
     */
    protected $conn;

    /**
     * Creates a new DatabaseService that operates on the given entity manager
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->conn = $entityManager->getConnection();
    }

    public function beginTransaction(): void
    {
        $this->conn->beginTransaction();
    }

    public function commit(): void
    {
        $this->conn->commit();
    }

    public function rollBack(): void
    {
        $this->conn->rollBack();
    }

    public function updateModified(int $entityId): int
    {
        return $this->conn->executeUpdate(
            'UPDATE data.entity
            set modified = DEFAULT
            where entity.identity = ?',
            [$entityId]
        );
    }

    public function createRevision(
        string $type,
        int $id,
        string $userEmail,
        string $oldValue = null,
        string $newValue = null
    ): int {
        return $this->conn->executeUpdate(
            'INSERT INTO logic.revision (type, identity, created, user_email, old_value, new_value)
            values (?, ?, DEFAULT, ?, ?, ?)',
            [$type, $id, $userEmail, $oldValue, $newValue]
        );
    }
}
