<?php

namespace AppBundle\Model;

class Entity
{
    protected $id;
    protected $publicComment;
    protected $privateComment;
    protected $public;
    protected $identifications;

    public function __construct()
    {
        $this->identifications = [];

        return $this;
    }

    public function setId(int $id): Entity
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setPublicComment(string $publicComment = null): Entity
    {
        $this->publicComment = $publicComment;

        return $this;
    }

    public function getPublicComment(): ?string
    {
        return $this->publicComment;
    }

    public function setPrivateComment(string $privateComment = null): Entity
    {
        $this->privateComment = $privateComment;

        return $this;
    }

    public function getPrivateComment(): ?string
    {
        return $this->privateComment;
    }

    public function setPublic(bool $public): Entity
    {
        $this->public = $public;

        return $this;
    }

    public function getPublic(): bool
    {
        return $this->public;
    }

    public function addIdentification(Identification $identification): Person
    {
        $this->identifications[$identification->getIdentifier()->getSystemName()] = $identification;

        return $this;
    }

    public function getIdentifications(): array
    {
        return $this->identifications;
    }
}
