<?php

namespace AppBundle\Model;

class Identifier extends IdNameObject
{
    const CACHENAME = 'identifier';

    private $systemName;
    private $primary;
    private $ids = [];
    private $regex;
    private $description;
    private $link;
    private $linkType;
    private $extra;
    private $extraRequired;

    public function __construct(
        int $id,
        string $systemName,
        string $name,
        bool $primary,
        string $link = null,
        string $linkType = null,
        array $ids = null,
        string $regex = null,
        string $description = null,
        bool $extra = null,
        bool $extraRequired = null
    ) {
        parent::__construct($id, $name);

        $this->systemName = $systemName;
        $this->primary = $primary;
        $this->link = $link;
        $this->linkType = $linkType;
        $this->ids = $ids;
        $this->regex = $regex;
        $this->description = $description;
        $this->extra = $extra !== null ? $extra : null;
        $this->extraRequired = $extraRequired !== null ? $extraRequired : null;
    }

    public function getSystemName(): string
    {
        return $this->systemName;
    }

    public function getPrimary(): bool
    {
        return $this->primary;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getLinkType(): ?string
    {
        return $this->linkType;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getVolumes(): int
    {
        return count($this->ids);
    }

    public function getRegex(): string
    {
        return $this->regex;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getExtra(): bool
    {
        return $this->extra;
    }

    public function getExtraRequired(): bool
    {
        return $this->extraRequired;
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'systemName' => $this->systemName,
            'name' => $this->name,
            'extra' => $this->extra,
            'extraRequired' => $this->extraRequired,
            'volumes' => $this->getVolumes(),
        ];
        if (isset($this->regex)) {
            $result['regex'] = $this->regex;
        }
        if (isset($this->description)) {
            $result['description'] = $this->description;
        }
        return $result;
    }
}
