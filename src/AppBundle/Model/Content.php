<?php

namespace AppBundle\Model;

class Content extends IdNameObject
{
    const CACHENAME = 'content';

    use CacheLinkTrait;
}
