<?php

namespace AppBundle\Service\ElasticSearchService;

use stdClass;

use Elastica\Document;
use Elastica\Type;

use AppBundle\Model\IdElasticInterface;

class ElasticBaseService extends ElasticSearchService
{
    public function updateRoleMapping(): void
    {
        $mapping = new Type\Mapping;
        $mapping->setType($this->type);
        foreach ($this->getRoleSystemNames(true) as $role) {
            $properties[$role] = ['type' => 'nested'];
            $properties[$role . '_public'] = ['type' => 'nested'];
        }
        $mapping->setProperties($properties);
        $mapping->send();
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

    /**
     * Get the ids of all entities satisfying a certain filter.
     * @param  stdClass $filter
     * @return array
     */
    public function getAllResults(stdClass $filter): array
    {
        $params = [
            'limit' => 1000,
            'page' => 0,
        ];
        $params['filters'] = $this->classifyFilters(json_decode(json_encode($filter), true), true);
        $params['sort'] = ['id' => 'asc'];

        $ids = [];
        $offset = 0;
        do {
            $params['page']++;
            $results = $this->search($params);
            foreach ($results['data'] as $result) {
                $ids[] = $result['id'];
            }
        } while ($params['page'] * $params['limit'] < $results['count']);

        return $ids;
    }
}
