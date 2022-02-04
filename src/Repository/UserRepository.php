<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

use App\Entity\User;

class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, User::class);
    }

    public function findOneBy(array $criteria, array $orderBy = null): ?User
    {
        if (isset($criteria['username'])) {
            $criteria['username'] = strtolower($criteria['username']);
        }
        return parent::findOneBy($criteria, $orderBy);
    }

    public function loadUserByUsername($username): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :username')
            ->setParameter('username', strtolower($username))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
