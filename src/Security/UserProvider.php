<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use App\Entity\User;
use App\Repository\UserRepository;

class UserProvider implements UserProviderInterface
{
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        $user = $this->userRepository->loadUserByUsername($username);

        if (!$user) {
            return (new User())->setUsername(strtolower($username));
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $id = $user->getId();

        if ($id == null) {
            return $user;
        } else {
            $reloadUser = $this->userRepository->findOneBy(['id' => $id]);

            if (!$reloadUser) {
                return (new User())->setUsername($user->getUsername());
            }

            return $reloadUser;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return User::class === $class;
    }
}