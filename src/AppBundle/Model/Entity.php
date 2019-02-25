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
     * Structure: [
     *      identifierSystemName => [ Identifier, [Identification, Identification, ...] ],
     *      identifierSystemName => [ Identifier, [Identification, Identification, ...] ],
     * ]
     */
    protected $identifications = [];
    /**
     * @var array
     */
    protected $inverseIdentifications = [];
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

    /**
     * @param array $identifications Strucuture: Identifier $identifier, array $identifications
     * @return Entity
     */
    public function addIdentifications(array $identifications): Entity
    {
        $this->identifications[$identifications[0]->getSystemName()] = $identifications;

        return $this;
    }

    public function getIdentifications(): array
    {
        return $this->identifications;
    }

    public function getFlatIdentifications(): array
    {
        $result = [];
        foreach ($this->identifications as $identification) {
            $result = array_merge($result, $identification[1]);
        }
        return $result;
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

    public function setInverseIdentifications(array $inverseIdentifications): Entity
    {
        $this->inverseIdentifications = $inverseIdentifications;

        return $this;
    }

    public function addInverseIdentification(Entity $entity, string $type): Entity
    {
        if (!isset($this->inverseIdentifications[$type])) {
            $this->inverseIdentifications[$type] = [];
        }
        $this->inverseIdentifications[$type][$entity->getId()] = $entity;

        return $this;
    }

    public function getInverseIdentifications(): array
    {
        return $this->inverseIdentifications;
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

    public function getInverseBibliographies(): array
    {
        return $this->inverseBibliographies;
    }

    public function getInverseReferences(): array
    {
        $inverseReferences = [];

        // Bibliographies
        foreach ($this->inverseBibliographies as $type => $entities) {
            $inverseReferences[$type] = [];
            foreach ($entities as $id => $entity) {
                $inverseReferences[$type][$id] = [$entity, ['bibliography']];
            }
        }

        // Identifications
        foreach ($this->inverseIdentifications as $type => $entities) {
            if (!isset($inverseReferences[$type])) {
                $inverseReferences[$type] = [];
            }
            foreach ($entities as $id => $entity) {
                if (!isset($inverseReferences[$type][$id])) {
                    $inverseReferences[$type][$id] = [$entity, ['identification']];
                } else {
                    $inverseReferences[$type][$id][1][] = 'identification';
                }
            }
        }

        // Sort
        foreach (array_keys($inverseReferences) as $type) {
            usort(
                $inverseReferences[$type],
                function($a, $b) {
                    return $a[0]->getId() > $b[0]->getId();
                }
            );
        }

        return $inverseReferences;
    }

    public function getPublicInverseReferences(): array
    {
        $inverseReferences = $this->getInverseReferences();

        foreach ($inverseReferences as $type => $entitiesWithReferenceTypes) {
            foreach ($entitiesWithReferenceTypes as $id => $entityWithReferenceTypes) {
                if (!$entityWithReferenceTypes->getPublic()) {
                    unset($inverseReferences[$type][$id]);
                }
            }
            if(empty($inverseReferences[$type])) {
                unset($inverseReferences[$type]);
            }
        }

        return $inverseReferences;
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
            foreach ($this->identifications as $identifications) {
                $result['identifications'][$identifications[0]->getSystemName()] = arrayToJson::arrayToJson($identifications[1]);
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

        foreach ($this->identifications as $identifications) {
            if ($identifications[0]->getPrimary()) {
                $result[$identifications[0]->getSystemName()] =
                    array_map(
                        function($identification) {
                            return $identification->getVolumeIdentification();
                        },
                        $identifications[1]
                    );
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
