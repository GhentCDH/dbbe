<?php

namespace AppBundle\Model;

class Status extends IdNameObject
{
    const CACHENAME = 'status';
    
    private $type;

    public function __construct(int $id, string $name, string $type = null)
    {
        parent::__construct($id, $name);

        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
        ];
    }
}
