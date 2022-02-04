<?php

namespace App\Model;

class BibRole
{
    private $person;
    private $document;
    private $role;

    public function __construct(Person $person = null, Document $document = null, Role $role)
    {
        $this->person = $person;
        $this->document = $document;
        $this->role = $role;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getRole(): Role
    {
        return $this->role;
    }
}
