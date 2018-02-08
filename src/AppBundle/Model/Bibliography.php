<?php

namespace AppBundle\Model;

abstract class Bibliography
{
    use CacheDependenciesTrait;

    protected $id;
    protected $type;

    public function __construct(int $id, string $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    abstract public function getDescription(): string;

    protected static function formatPages(
        string $page_start = null,
        string $page_end = null,
        string $prefix = ''
    ): string {
        if (empty($page_start)) {
            return '';
        }
        if (empty($page_end)) {
            return $prefix . $page_start;
        }
        return $prefix . $page_start . '-' . $page_end;
    }
}
