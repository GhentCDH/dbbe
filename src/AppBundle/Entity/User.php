<?php

namespace AppBundle\Entity;

use DateTime;
use Serializable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRepository")
 * @ORM\Table(name="user", schema="logic")
 * @UniqueEntity("email")
 */
class User implements Serializable, UserInterface, EquatableInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\Column(name="username", type="string")
     */
    protected $username;

    /**
     * @ORM\Column(type="array")
     */
    private $roles = ['ROLE_USER'];


    /**
     * @ORM\Column(name="created", type="datetime")
     */
    protected $created;

    /**
     * @ORM\Column(name="modified", type="datetime")
     */
    protected $modified;

    /**
     * @ORM\Column(name="last_login", type="datetime", nullable=true)
     */
    protected $lastLogin;

    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        if (empty($this->roles)) {
            return ['ROLE_USER'];
        }
        return $this->roles;
    }

    public function setRoles(array $roles)
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;

        return $this;
    }

    public function setLastLogin(DateTime $dateTime)
    {
        $this->lastLogin = $dateTime;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        return null;
    }

    public function serialize()
    {
        return serialize(
            [
                $this->id,
                $this->username,
                $this->roles,
            ]
        );
    }

    public function unserialize($serialized)
    {
        list (
            $this->id,
            $this->username,
            $this->roles
        ) = unserialize($serialized);
    }

    public function isEqualTo(UserInterface $user)
    {
        if (!($user instanceof User)) {
            return false;
        }

        if ($this->username !== $user->getUsername()) {
            return false;
        }

        // Check that the roles are the same, in any order
        if (count($this->getRoles()) != count($user->getRoles())) {
            return false;
        }
        foreach($this->getRoles() as $role) {
            if (!in_array($role, $user->getRoles())) {
                return false;
            }
        }

        return true;
    }
}
