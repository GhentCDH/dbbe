<?php

namespace AppBundle\Service\DatabaseService;

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
        $this->cache = $cacheItemPool;
    }
}
