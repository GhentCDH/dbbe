<?php

namespace AppBundle\Service\ElasticSearchService;

use Elastica\Document;

use AppBundle\Model\Entity;

class ElasticEntityService extends ElasticSearchService
{
    public function addMultiple(array $entities): void
    {
        $elastic = [];
        foreach ($entities as $entity) {
            $elastic [] = $entity->getElastic();
        }

        $this->bulkAdd($elastic);
    }

    public function add(Entity $entity)
    {
        $indexingContent = $entity->getElastic();
        $document = new Document($indexingContent['id'], $indexingContent);
        $this->type->addDocument($document);
    }

    public function delete(Entity $entity)
    {
        $document = new Document($entity->getId(), []);
        $this->type->deleteDocument($document);
    }
}
