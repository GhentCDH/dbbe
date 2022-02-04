<?php

namespace App\ElasticSearchService;

use stdClass;

use Elastica\Document;
use Elastica\Mapping;

use App\Model\IdElasticInterface;

abstract class ElasticBaseService extends ElasticSearchService
{
    public function updateRoleMapping(): void
    {
        $properties = [];
        foreach ($this->getRoleSystemNames(true) as $role) {
            $properties[$role] = ['type' => 'nested'];
            $properties[$role . '_public'] = ['type' => 'nested'];
        }
        $this->index->setMapping(new Mapping($properties));
    }

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
            $this->index->addDocuments($bulk_documents);
            $bulk_documents = [];
        }
        $this->index->refresh();
    }

    public function add(IdElasticInterface $entity): void
    {
        $indexingContent = $entity->getElastic();
        $document = new Document($indexingContent['id'], $indexingContent);
        $this->index->addDocument($document);
        $this->index->refresh();
    }

    public function deleteMultiple(array $ids): void
    {
        $this->index->deleteIds($ids);
        $this->index->refresh();
    }

    public function delete(int $id): void
    {
        $this->index->deleteById($id);
        $this->index->refresh();
    }

    public function updateMultiple(array $data): void
    {
        $bulk_documents = [];
        while (count($data) > 0) {
            $bulk_contents = array_splice($data, 0, 500);
            foreach ($bulk_contents as $bc) {
                $bulk_documents[] = new Document($bc['id'], $bc);
            }
            $this->index->updateDocuments($bulk_documents);
            $bulk_documents = [];
        }
        $this->index->refresh();
    }
}
