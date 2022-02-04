<?php

namespace App\Model;

interface IdElasticInterface
{
    public function getId(): int;
    public function getElastic(): array;
}
