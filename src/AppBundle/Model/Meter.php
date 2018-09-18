<?php

namespace AppBundle\Model;

/**
 */
class Meter extends IdNameObject
{
    /**
     * @var string
     */
    const CACHENAME = 'meter';

    use CacheLinkTrait;
}
