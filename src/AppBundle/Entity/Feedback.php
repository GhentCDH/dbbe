<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="feedback", schema="logic")
 */
class Feedback
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=4000)
     */
    private $url;

    /**
     * @ORM\Column(type="string", length=4000)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=4000)
     */
    private $message;

    /**
     * @ORM\Column(type="datetime", options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created;

    /**
     * @ORM\Column(type="string", length=40, options={"default"="new"})
     */
    private $status;
}
