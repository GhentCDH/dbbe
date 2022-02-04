<?php

namespace App\Model;

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
    private $order;

    public function __construct(int $id, array $usage, string $systemName, string $name, bool $contributorRole, bool $rank, int $order=null)
    {
        parent::__construct($id, $name);

        $this->usage = $usage;
        $this->systemName = $systemName;
        $this->contributorRole = $contributorRole;
        $this->rank = $rank;
        $this->order = $order;
    }

    public static function getContentRole(string $systemName = null): Role
    {
        return new Role(1000, ['manuscript'], $systemName ?? 'content', 'Content', false, false);
    }

    public static function getSubjectRole(string $systemName = null): Role
    {
        return new Role(1001, ['occurrence', 'type'], $systemName ?? 'subject', 'Subject', false, false);
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

    public function getOrder(): ?int
    {
        return $this->order;
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
