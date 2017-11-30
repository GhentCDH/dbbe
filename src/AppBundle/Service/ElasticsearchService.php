<?php

namespace AppBundle\Service;

use Elastica\Client;

class ElasticsearchService
{
    protected $client;
    protected $index;

    public function __construct($hosts, $index)
    {
        $this->client = new Client($hosts);
        $this->index = $this->client->getIndex($index);
    }

    public function getIndex()
    {
        return $this->index;
    }
}
