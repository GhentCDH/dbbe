<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

class UserRepository extends EntityRepository implements UserLoaderInterface
{
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        if (isset($criteria['username'])) {
            $criteria['username'] = strtolower($criteria['username']);
        }
        return parent::findOneBy($criteria, $orderBy);
    }

    public function loadUserByUsername($username)
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :username')
            ->setParameter('username', strtolower($username))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
