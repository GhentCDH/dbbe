<?php

namespace App\Model;

interface SubjectInterface
{
    public function getId(): int;
    public function getShortJson(): array;
}
