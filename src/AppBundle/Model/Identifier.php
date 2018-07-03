<?php

namespace AppBundle\Model;

class Identifier
{
    private $id;
    private $systemName;
    private $name;
    private $primary;
    private $volumes;
    private $regex;
    private $description;
    private $link;

    public function __construct(int $id, string $systemName, string $name, bool $primary, string $link = null, int $volumes = null, string $regex = null, string $description = null)
    {
        $this->id = $id;
        $this->systemName = $systemName;
        $this->name = $name;
        $this->primary = $primary;
        $this->link = $link;
        $this->volumes = $volumes;
        $this->regex = $regex;
        $this->description = $description;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSystemName(): string
    {
        return $this->systemName;
    }

    public function getName(): string
    {
        return $this->name;
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

    public function getJson(): array
    {
        $result = [
            'systemName' => $this->systemName,
            'name' => $this->name,
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
