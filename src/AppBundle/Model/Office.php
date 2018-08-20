<?php

namespace AppBundle\Model;

class Office extends IdNameObject
{
    const CACHENAME = 'office';

    use CacheLinkTrait;
}
