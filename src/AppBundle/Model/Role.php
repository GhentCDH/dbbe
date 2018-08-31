<?php

namespace AppBundle\Model;

/**
 * Role with id 0 is reserved for the pseudo role 'subject'
 */
class Role extends IdNameObject
{
    const CACHENAME = 'role';

    use CacheLinkTrait;

    private $usage;
    private $systemName;

    public function __construct(int $id, array $usage, string $systemName, string $name)
    {
        $this->id = $id;
        $this->usage = $usage;
        $this->systemName = $systemName;
        $this->name = $name;
    }

    public function getUsage(): array
    {
        return $this->uage;
    }

    public function getSystemName(): string
    {
        return $this->systemName;
    }

    public function getJson(): array
    {
        return [
            'id' => $this->id,
            'usage' => $this->usage,
            'systemName' => $this->systemName,
            'name' => $this->name,
        ];
    }
}
