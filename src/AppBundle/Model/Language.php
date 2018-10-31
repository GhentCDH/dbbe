<?php

namespace AppBundle\Model;

class Language extends IdNameObject
{
    private $code;

    public function setCode(string $code): Language
    {
        $this->code = $code;

        return $this;
    }

    public function getcode(): string
    {
        return $this->code;
    }
}
