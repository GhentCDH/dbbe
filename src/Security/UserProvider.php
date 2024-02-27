<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;

use App\Entity\User;
use App\Repository\UserRepository;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthUser;

class UserProvider implements UserProviderInterface, OAuthAwareUserProviderInterface
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
        return $this->loadUserByIdentifier($username);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByIdentifier($identifier)
    {
        $user = $this->userRepository->loadUserByUsername($identifier);

        if (!$user) {
            return (new User())->setUsername(strtolower($identifier));
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
        return $class === OAuthUser::class;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $user = $this->loadUserByIdentifier($response->getUserIdentifier());

        if (!$user) {
            return (new User())->setUsername(strtolower($response->getUserIdentifier()));
        }

        return $user;
    }
}