<?php

namespace AppBundle\Security;

use FOS\UserBundle\Security\UserProvider as BaseUserProvider;

/**
 * Altered version of FOSUserBundle UserProvider.
 * When a user cannot be loaded by username, a new user is created.
 */
class UserProvider extends BaseUserProvider
{
    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        $user = $this->findUser($username);
        if (!$user) {
            $user = $this->userManager->createUser();
            $user->setUsername($username);
            $user->setEmail($username);
            $user->setPassword($username);
            $this->userManager->updateUser($user);
        }
        return $user;
    }
}
