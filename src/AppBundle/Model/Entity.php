<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Entity implements IdJsonInterface
{
    use CacheObjectTrait;

    protected $id;
    protected $publicComment;
    protected $privateComment;
    protected $public;
    protected $identifications;
    protected $bibliographies;
    protected $inverseBibliographies;

    protected $cacheLevel;

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

    public function setCacheLevel(string $cacheLevel): Entity
    {
        $this->cacheLevel = $cacheLevel;

        return $this;
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

    public function setIdentifications(array $identifications): Entity
    {
        $this->identifications = $identifications;

        return $this;
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

    public function setBibliographies(array $bibliographies): Entity
    {
        $this->bibliographies = $bibliographies;

        return $this;
    }

    public function addBibliography(Bibliography $bibliography): Entity
    {
        $this->bibliographies[$bibliography->getId()] = $bibliography;

        return $this;
    }

    public function getBibliographies(): ?array
    {
        return $this->bibliographies;
    }

    public function setInverseBibliographies(array $inverseBibliographies): Entity
    {
        $this->inverseBibliographies = $inverseBibliographies;

        return $this;
    }

    public function addInverseBibliography(Entity $entity, string $type): Entity
    {
        if (!isset($this->inverseBibliographies[$type])) {
            $this->inverseBibliographies[$type] = [];
        }
        $this->inverseBibliographies[$type][$entity->getId()] = $entity;

        return $this;
    }

    public function sortInverseBibliographies(): void
    {
        foreach ($this->inverseBibliographies as $type => $array) {
            usort(
                $this->inverseBibliographies[$type],
                function ($a, $b) use ($type) {
                    switch ($type) {
                        case 'manuscript':
                        case 'person':
                            return strcmp($a->getDescription(), $b->getDescription());
                            break;
                        case 'occurrence':
                        case 'type':
                            return strcmp($a->getIncipit(), $b->getIncipit());
                            break;
                    }
                }
            );
        }
    }

    public function getInverseBibliographies(): ?array
    {
        return $this->inverseBibliographies;
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
        ];

        if (isset($this->publicComment)) {
            $result['publicComment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['privateComment'] = $this->privateComment;
        }
        if (isset($this->public)) {
            $result['public'] = $this->public;
        }
        if (!empty($this->identifications)) {
            $result['identifications'] = [];
            foreach ($this->identifications as $identification) {
                $result['identifications'][$identification->getIdentifier()->getSystemName()] = implode(', ', $identification->getIdentifications());
            }
        }
        if (!empty($this->getBibliographies())) {
            $result['bibliography'] = ArrayToJson::arrayToShortJson($this->getBibliographies());
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
