<?php

namespace App\Model;

interface IdJsonInterface
{
    public function getId(): int;
    public function getJson(): array;
}
