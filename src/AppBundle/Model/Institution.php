<?php

namespace AppBundle\Model;

class Institution extends IdNameObject
{
    const CACHENAME = 'institution';

    use CacheLinkTrait;
}
