<?php

namespace AppBundle\Model;

/**
 */
class TypeRelationType extends IdNameObject
{
    /**
     * @var string
     */
    const CACHENAME = 'type_relation_type';

    use CacheLinkTrait;
}
