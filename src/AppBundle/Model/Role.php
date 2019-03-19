<?php

namespace AppBundle\Model;

/**
 * Role with id 0 is reserved for the pseudo role 'subject'
 */
class Role extends IdNameObject
{
    const CACHENAME = 'role';

    private $usage;
    private $systemName;
    private $contributorRole;
    private $rank;

    public function __construct(int $id, array $usage, string $systemName, string $name, bool $contributorRole, bool $rank)
    {
        parent::__construct($id, $name);

        $this->usage = $usage;
        $this->systemName = $systemName;
        $this->contributorRole = $contributorRole;
        $this->rank = $rank;
    }

    public function getUsage(): array
    {
        return $this->usage;
    }

    public function getSystemName(): string
    {
        return $this->systemName;
    }

    public function getContributorRole(): bool
    {
        return $this->contributorRole;
    }

    public function getRank(): bool
    {
        return $this->rank;
    }

    public function getJson(): array
    {
        return [
            'id' => $this->id,
            'usage' => $this->usage,
            'systemName' => $this->systemName,
            'name' => $this->name,
            'contributorRole' => $this->contributorRole,
            'rank' => $this->rank,
        ];
    }
}
