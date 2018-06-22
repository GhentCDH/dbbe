<?php

namespace AppBundle\Model;

class Occupation extends IdNameObject
{
    private $isFunction;

    public function __construct(int $id, string $name, bool $isFunction = null)
    {
        parent::__construct($id, $name);

        $this->isFunction = empty($isFunction) ? false : $isFunction;

        return $this;
    }

    public function getIsFunction(): bool
    {
        return $this->isFunction;
    }
}
