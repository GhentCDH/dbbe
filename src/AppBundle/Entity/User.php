<?php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

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

    public function getCreated()
    {
        return $this->created;
    }

    public function getModified()
    {
        return $this->modified;
    }
}
