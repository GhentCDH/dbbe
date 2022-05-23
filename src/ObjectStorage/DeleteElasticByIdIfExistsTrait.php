<?php

namespace App\ObjectStorage;

trait DeleteElasticByIdIfExistsTrait
{
    /**
     * Remove a document from elasticsearch if it exists
     * @param  int  $id
     */
    public function deleteElasticByIdIfExists(int $id): void
    {
        try {
            $this->ess->delete($id);
        } catch (\Elastica\Exception\NotFoundException $e) {
            // Ignore
        }
    }
}