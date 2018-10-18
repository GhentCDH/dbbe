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

        $bulk_documents = [];
        while (count($elastics) > 0) {
            $bulk_contents = array_splice($elastics, 0, 500);
            foreach ($bulk_contents as $bc) {
                $bulk_documents[] = new Document($bc['id'], $bc);
            }
            $this->type->addDocuments($bulk_documents);
            $bulk_documents = [];
        }
        $this->type->getIndex()->refresh();
    }

    public function add(IdElasticInterface $entity): void
    {
        $indexingContent = $entity->getElastic();
        $document = new Document($indexingContent['id'], $indexingContent);
        $this->type->addDocument($document);
        $this->type->getIndex()->refresh();
    }

    public function deleteMultiple(array $ids): void
    {
        $this->type->deleteIds($ids);
        $this->type->getIndex()->refresh();
    }

    public function delete(int $id): void
    {
        $this->type->deleteById($id);
        $this->type->getIndex()->refresh();
    }

    public function updateMultiple(array $data): void
    {
        $bulk_documents = [];
        while (count($data) > 0) {
            $bulk_contents = array_splice($data, 0, 500);
            foreach ($bulk_contents as $bc) {
                $bulk_documents[] = new Document($bc['id'], $bc);
            }
            $this->type->updateDocuments($bulk_documents);
            $bulk_documents = [];
        }
        $this->type->getIndex()->refresh();
    }
}
