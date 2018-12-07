<?php

namespace AppBundle\Model;

use DateTime;

use AppBundle\Utils\ArrayToJson;

class Entity implements IdJsonInterface, IdElasticInterface
{
    /**
     * @var int
     */
    protected $id;
    /**
     * @var string
     */
    protected $publicComment;
    /**
     * @var string
     */
    protected $privateComment;
    /**
     * @var bool
     */
    protected $public;
    /**
     * @var DateTime
     */
    protected $modified;
    /**
     * @var array
     */
    protected $identifications = [];
    /**
     * @var array
     */
    protected $bibliographies = [];
    /**
     * @var array
     */
    protected $inverseBibliographies = [];
    /**
     * @var array
     */
    protected $managements = [];

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

    public function setModified(DateTime $modified): Entity
    {
        $this->modified = $modified;

        return $this;
    }

    public function getModified(): DateTime
    {
        return $this->modified;
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

    public function getBibliographies(): array
    {
        return $this->bibliographies;
    }

    public function getTextSources(): array
    {
        return array_filter(
            $this->bibliographies,
            function ($bibliography) {
                return (
                    !empty($bibliography->getReferenceType())
                    && $bibliography->getReferenceType()->getName() == ReferenceType::TEXT_SOURCE
                );
            }
        );
    }

    public function getPrimarySources(): array
    {
        return array_filter(
            $this->bibliographies,
            function ($bibliography) {
                return (
                    !empty($bibliography->getReferenceType())
                    && $bibliography->getReferenceType()->getName() == ReferenceType::PRIMARY_SOURCE
                );
            }
        );
    }

    public function getSecondarySources(): array
    {
        return array_filter(
            $this->bibliographies,
            function ($bibliography) {
                return (
                    !empty($bibliography->getReferenceType())
                    && $bibliography->getReferenceType()->getName() == ReferenceType::SECONDARY_SOURCE
                );
            }
        );
    }

    public function getOtherSources(): array
    {
        return array_filter(
            $this->bibliographies,
            function ($bibliography) {
                return (
                    empty($bibliography->getReferenceType())
                    || !in_array(
                        $bibliography->getReferenceType()->getName(),
                        [
                            ReferenceType::TEXT_SOURCE,
                            ReferenceType::PRIMARY_SOURCE,
                            ReferenceType::SECONDARY_SOURCE,
                        ]
                    )
                );
            }
        );
    }

    public function getImageBibliographies(): array
    {
        return array_filter(
            $this->bibliographies,
            function ($bibliography) {
                return (!empty($bibliography->getImage()));
            }
        );
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

    public function getInverseBibliographies(): array
    {
        return $this->inverseBibliographies;
    }

    public function setManagements(array $managements): Entity
    {
        $this->managements = $managements;

        return $this;
    }

    public function addManagement(Management $management): Entity
    {
        $this->managements[$management->getId()] = $management;

        return $this;
    }

    public function getManagements(): array
    {
        return $this->managements;
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
                $result['identifications'][$identification->getIdentifier()->getSystemName()] =
                    implode(', ', $identification->getIdentifications());
                $result['identifications'][$identification->getIdentifier()->getSystemName() . '_extra'] =
                    $identification->getExtra();
            }
        }
        if (!empty($this->bibliographies)) {
            $result['bibliography'] = ArrayToJson::arrayToShortJson($this->bibliographies);
        }
        if (!empty($this->managements)) {
            $result['managements'] = ArrayToJson::arrayToShortJson($this->managements);
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
        if (isset($this->managements)) {
            $result['management'] = ArrayToJson::arrayToShortJson($this->managements);
        }

        return $result;
    }
}
