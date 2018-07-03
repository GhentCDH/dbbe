<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Identifier;

class IdentifierManager extends ObjectManager
{
    public function getIdentifiersByType(string $type): array
    {
        $cache = $this->cache->getItem('identifiers.' . $type);
        if ($cache->isHit()) {
            return $cache->get();
        }

        $identifiers = [];
        $rawIdentifiers = $this->dbs->getIdentifiersByType($type);

        foreach ($rawIdentifiers as $rawIdentifier) {
            $identifiers[$rawIdentifier['system_name']] = new Identifier(
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

        $cache->tag(['identifiers']);
        $this->cache->save($cache->set($identifiers));
        return $identifiers;
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
