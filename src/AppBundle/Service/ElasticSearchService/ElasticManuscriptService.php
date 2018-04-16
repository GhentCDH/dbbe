<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Type;

use AppBundle\Model\Manuscript;

class ElasticManuscriptService extends ElasticSearchService
{
    public function setupManuscripts(): void
    {
        $type = $this->getIndex('documents')->getType('manuscript');

        $mapping = new Type\Mapping;
        $mapping->setType($type);

        $mapping->setProperties(
            [
                'content' => ['type' => 'nested'],
                'patron' => ['type' => 'nested'],
                'scribe' => ['type' => 'nested'],
                'origin' => ['type' => 'nested'],
            ]
        );
        $mapping->send();
    }

    public function addManuscripts(array $manuscripts): void
    {
        $type = $this->getIndex('documents')->getType('manuscript');

        $manuscriptsElastic = [];
        foreach ($manuscripts as $manuscript) {
            $manuscriptsElastic [] = $manuscript->getElastic();
        }

        $this->bulkAdd($type, $manuscriptsElastic);
    }

    public function addManuscript(Manuscript $manuscript): void
    {
        $type = $this->getIndex('documents')->getType('manuscript');
        $this->add($type, $manuscript->getElastic());
    }
}
