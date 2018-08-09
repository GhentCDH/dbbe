<?php

namespace AppBundle\Model;

class Entity
{
    protected $id;
    protected $publicComment;
    protected $privateComment;
    protected $public;
    protected $identifications;
    protected $bibliographies;

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

    public function addIdentification(Identification $identification): Entity
    {
        $this->identifications[$identification->getIdentifier()->getSystemName()] = $identification;

        return $this;
    }

    public function getIdentifications(): array
    {
        return $this->identifications;
    }

    public function addBibliography(Bibliography $bibliography): Document
    {
        $this->bibliographies[$bibliography->getId()] = $bibliography;

        return $this;
    }

    public function getBibliographies(): array
    {
        return $this->bibliographies;
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'public' => $this->public,
        ];

        if (count($this->identifications) > 0) {
            $result['identifications'] = [];
            foreach ($this->identifications as $identification) {
                $result['identifications'][$identification->getIdentifier()->getSystemName()] = implode(', ', $identification->getIdentifications());
            }
        }

        if (isset($this->publicComment)) {
            $result['publicComment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['privateComment'] = $this->privateComment;
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'public' => $this->public,
        ];

        foreach ($this->identifications as $identification) {
            if ($identification->getIdentifier()->getPrimary()) {
                $result[$identification->getIdentifier()->getSystemName()] = $identification->getIdentifications();
            }
        }

        if (isset($this->publicComment)) {
            $result['public_comment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['private_comment'] = $this->privateComment;
        }

        return $result;
    }
}
