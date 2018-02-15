<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

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
     * The cache to store database query results.
     * @var \Symfony\Component\Cache\Adapter\ApcuAdapter
     */
    protected $cache;

    /**
     * Creates a new DatabaseService that operates on the given entity manager
     * @param EntityManagerInterface $entityManager
     * @param CacheItemPoolInterface $cacheItemPool
     */
    public function __construct(EntityManagerInterface $entityManager, CacheItemPoolInterface $cacheItemPool)
    {
        $this->conn = $entityManager->getConnection();
        $this->conn->setTransactionIsolation(Connection::TRANSACTION_SERIALIZABLE);
        $this->cache = $cacheItemPool;
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
        int $entityId,
        int $userId,
        string $oldValue,
        string $newValue
    ): int {
        return $this->conn->executeUpdate(
            'INSERT INTO logic.revision (identity, created, iduser, old_value, new_value)
            values (?, DEFAULT, ?, ?, ?)',
            [$entityId, $userId, $oldValue, $newValue]
        );
    }
}
