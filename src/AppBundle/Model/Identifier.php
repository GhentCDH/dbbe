<?php

namespace AppBundle\Model;

class Identifier extends IdNameObject
{
    const CACHENAME = 'identifier';

    private $systemName;
    private $primary;
    private $volumes;
    private $regex;
    private $description;
    private $link;
    private $extra;
    private $hideVolume;

    public function __construct(
        int $id,
        string $systemName,
        string $name,
        bool $primary,
        string $link = null,
        int $volumes = null,
        string $regex = null,
        string $description = null,
        bool $extra = null,
        bool $hideVolume = null
    ) {
        $this->id = $id;
        $this->systemName = $systemName;
        $this->name = $name;
        $this->primary = $primary;
        $this->link = $link;
        $this->volumes = $volumes;
        $this->regex = $regex;
        $this->description = $description;
        $this->extra = $extra !== null ? $extra : null;
        $this->hideVolume = $hideVolume !== null ? $hideVolume : null;
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

    public function getVolumes(): int
    {
        return $this->volumes;
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

    public function getHideVolume(): bool
    {
        return $this->hideVolume;
    }

    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'systemName' => $this->systemName,
            'name' => $this->name,
            'extra' => $this->extra,
            'hideVolume' => $this->hideVolume,
        ];
        if (isset($this->volumes)) {
            $result['volumes'] = $this->volumes;
        }
        if (isset($this->regex)) {
            $result['regex'] = $this->regex;
        }
        if (isset($this->description)) {
            $result['description'] = $this->description;
        }
        return $result;
    }
}
