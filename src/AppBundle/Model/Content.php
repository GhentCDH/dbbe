<?php

namespace AppBundle\Model;

class Content extends IdNameObject
{
    const CACHENAME = 'content';

    private $person;

    public function __construct(
        int $id,
        string $name = null,
        Person $person = null
    ) {
        parent::__construct($id, $name);
        $this->person = $person;
        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function getDisplayName(): ?string
    {
        if ($this->person != null) {
            return $this->person->getDescription();
        }
        return $this->name;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDisplayName(),
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();
        $result['person'] = $this->person->getShortJson();
        return $result;
    }
}
