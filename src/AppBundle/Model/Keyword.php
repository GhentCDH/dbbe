<?php

namespace AppBundle\Model;

class Keyword extends IdNameObject implements SubjectInterface
{
    const CACHENAME = 'keyword';

    use CacheLinkTrait;
}
