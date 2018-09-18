<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Identifier;

class IdentifierManager extends ObjectManager
{
    public function get(array $ids)
    {
        return $this->wrapCache(
            Identifier::CACHENAME,
            $ids,
            function ($ids) {
                $rawIdentifiers = $this->dbs->getIdentifiersByIds($ids);
                $identifiers = $this->getWithData($rawIdentifiers);

                return $identifiers;
            }
        );
    }

    public function getWithData(array $data)
    {
        return $this->wrapDataCache(
            Identifier::CACHENAME,
            $data,
            'identifier_id',
            function ($data) {
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
                            $rawIdentifier['volumes'],
                            $rawIdentifier['regex'],
                            $rawIdentifier['description']
                        );
                    }
                }

                return $identifiers;
            }
        );
    }

    public function getIdentifiersByType(string $type): array
    {
        return $this->wrapArrayTypeCache(
            'identifiers',
            $type,
            ['identifiers'],
            function ($type) {
                $identifiers = [];
                $rawIdentifiers = $this->dbs->getIdentifiersByType($type);

                // Keys in this array must be systemnames as they are used in queries
                $identifiersWithId = $this->getWithData($rawIdentifiers);
                foreach ($identifiersWithId as $identifierWithId) {
                    $identifiers[$identifierWithId->getSystemName()] = $identifierWithId;
                }

                return $identifiers;
            }
        );
    }

    public function getPrimaryIdentifiersByType(string $type): array
    {
        return array_filter($this->getIdentifiersByType($type), function ($identifier) {
            return $identifier->getPrimary();
        });
    }

    public function getSecondaryIdentifiersByType(string $type): array
    {
        return array_filter($this->getIdentifiersByType($type), function ($identifier) {
            return !$identifier->getPrimary();
        });
    }
}
