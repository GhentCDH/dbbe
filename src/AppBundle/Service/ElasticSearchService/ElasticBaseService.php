<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Document;

use AppBundle\Model\IdElasticInterface;

class ElasticBaseService extends ElasticSearchService
{
    public function addMultiple(array $entities): void
    {
        $elastics = [];
        foreach ($entities as $entity) {
            $elastics [] = $entity->getElastic();
        }

        $this->bulkAdd($elastics);
    }

    public function add(IdElasticInterface $entity)
    {
        $indexingContent = $entity->getElastic();
        $document = new Document($indexingContent['id'], $indexingContent);
        $this->type->addDocument($document);
    }

    public function deleteMultiple(array $ids)
    {
        $this->type->deleteIds($ids);
    }
}
