<?php

namespace AppBundle\ObjectStorage;

class EntityManager extends ObjectManager
{
    protected function setPublics(array &$entities): void
    {
        $rawPublics = $this->dbs->getPublics(self::getIds($entities));
        foreach ($rawPublics as $rawPublic) {
            $entities[$rawPublic['entity_id']]
                // default: true (if no value is set in the database)
                ->setPublic(isset($rawPublic['public']) ? $rawPublic['public'] : true);
        }
    }

    protected function setComments(array &$entities): void
    {
        $rawComments = $this->dbs->getComments(self::getIds($entities));
        foreach ($rawComments as $rawComment) {
            $entities[$rawComment['entity_id']]
                ->setPublicComment($rawComment['public_comment'])
                ->setPrivateComment($rawComment['private_comment']);
        }
    }

    protected static function getIds(array $entities): array
    {
        return array_map(function ($entity) {
            return $entity->getId();
        }, $entities);
    }
}
