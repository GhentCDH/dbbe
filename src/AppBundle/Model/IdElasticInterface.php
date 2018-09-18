<?php

namespace AppBundle\Model;

interface IdElasticInterface
{
    public function getId(): int;
    public function getElastic(): array;
}
