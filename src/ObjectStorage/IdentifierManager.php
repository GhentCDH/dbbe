<?php

namespace App\ObjectStorage;

use App\Model\Identifier;

use App\Utils\ArrayToJson;

class IdentifierManager extends ObjectManager
{
    public function get(array $ids)
    {
        $rawIdentifiers = $this->dbs->getIdentifiersByIds($ids);
        $identifiers = $this->getWithData($rawIdentifiers);
    }

    public function getWithData(array $data)
    {
        $identifiers = [];
        foreach ($data as $rawIdentifier) {
            if (isset($rawIdentifier['identifier_id'])
                && !isset($identifiers[$rawIdentifier['identifier_id']])
            ) {
                $identifiers[$rawIdentifier['identifier_id']] = new Identifier(
                    $rawIdentifier['identifier_id'],
                    $rawIdentifier['system_name'],
                    $rawIdentifier['name'],
                    $rawIdentifier['is_primary'],
                    $rawIdentifier['link'],
                    $rawIdentifier['link_type'],
                    json_decode($rawIdentifier['ids']),
                    $rawIdentifier['regex'],
                    $rawIdentifier['description'],
                    $rawIdentifier['extra'],
                    $rawIdentifier['extra_required'],
                    $rawIdentifier['cluster_id']
                );
            }
        }

        return $identifiers;
    }

    public function getByType(string $type): array
    {
        $identifiers = [];
        $rawIdentifiers = $this->dbs->getByType($type);

        // Keys in this array must be systemnames as they are used in queries
        $identifiersWithId = $this->getWithData($rawIdentifiers);
        foreach ($identifiersWithId as $identifierWithId) {
            $identifiers[$identifierWithId->getSystemName()] = $identifierWithId;
        }

        return $identifiers;
    }

    public function getByTypeJson(string $type): array
    {
        return ArrayToJson::arrayToJson($this->getByType($type));
    }

    public function getPrimaryByType(string $type): array
    {
        return array_filter($this->getByType($type), function ($identifier) {
            return $identifier->getPrimary();
        });
    }

    public function getPrimaryByTypeJson(string $type): array
    {
        return ArrayToJson::arrayToJson(
            array_filter($this->getByType($type), function ($identifier) {
                return $identifier->getPrimary();
            })
        );
    }
}
