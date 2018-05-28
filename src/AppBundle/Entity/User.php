<?php

namespace AppBundle\Entity;

use stdClass;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user", schema="logic")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="full_name", type="string", nullable=true)
     */
    protected $fullName;

    /**
     * @ORM\Column(name="start_tenure", type="date", nullable=true)
     */
    protected $startTenure;

    /**
     * @ORM\Column(name="end_tenure", type="date", nullable=true)
     */
    protected $endTenure;

    /**
     * @ORM\Column(name="created", type="datetime")
     */
    protected $created;

    /**
     * @ORM\Column(name="modified", type="datetime")
     */
    protected $modified;

    public function __construct()
    {
        parent::__construct();

        // Set creation date
        if (empty($this->created)) {
            $this->created = new \DateTime();
        }

        // Set modification date
        $this->modified = new \DateTime();
    }

    public function getFullName()
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName)
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getModified()
    {
        return $this->modified;
    }

    public function setModified(\DateTime $dateTime)
    {
        $this->modified = $dateTime;

        return $this;
    }

    public function getJson()
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email == $this->username ? null : $this->email,
            'full name' => $this->fullName,
            'roles' => $this->getRoles(),
            'status' => $this->enabled,
            'created' => $this->created->format('Y-m-d H:i:s'),
            'modified' => $this->modified->format('Y-m-d H:i:s'),
            // last login is not required
            'last login' => $this->lastLogin ? $this->lastLogin->format('Y-m-d H:i:s') : null,
        ];
    }

    public function setFromJson(stdClass $data)
    {
        if (!empty($this->username) && isset($data->username) && $this->username != $data->username) {
            throw new BadRequestHttpException('Username already set');
        }
        if (empty($this->username) && (!isset($data->username) || !is_string($data->username))) {
            throw new BadRequestHttpException('Username is required');
        }
        if (empty($this->username) && isset($data->username) && is_string($data->username)) {
            $this->username = $data->username;
            # Email will be set using CAS attributes at first login
            $this->email = $data->username;
            # Password is not used
            $this->password = $data->username;
        }

        if (!isset($data->roles)) {
            $data->roles = [];
        }
        $addRoles = array_diff($data->roles, $this->getRoles());
        $removeRoles = array_diff($this->getRoles(), $data->roles);
        foreach ($addRoles as $role) {
            $this->addRole($role);
        }
        foreach ($removeRoles as $role) {
            $this->removeRole($role);
        }

        $this->setEnabled($data->status);
        $this->setModified(new \DateTime());
    }
}
