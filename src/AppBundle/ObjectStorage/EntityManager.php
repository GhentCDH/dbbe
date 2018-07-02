<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Model\Entity;
use AppBundle\Model\FuzzyDate;

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

    protected function updatePublic(Entity $entity, bool $public): void
    {
        $this->dbs->updatePublic($entity->getId(), $public);
    }

    protected function updateDate(Entity $entity, string $type, FuzzyDate $currentDate = null, stdClass $newdate = null): void
    {
        if (empty($newdate)) {
            $this->dbs->deleteDate($entity->getId(), $type);
        } elseif (!property_exists($newdate, 'floor') || (!empty($newdate->floor) && !is_string($newdate->floor))
            || !property_exists($newdate, 'ceiling') || (!empty($newdate->ceiling) && !is_string($newdate->ceiling))
        ) {
            throw new BadRequestHttpException('Incorrect date data.');
        } else {
            $dbDate = '('
                . (empty($newdate->floor) ? '-infinity' : $newdate->floor)
                . ', '
                . (empty($newdate->ceiling) ? 'infinity' : $newdate->ceiling)
                . ')';
            if (!isset($currentDate) || $currentDate->isEmpty()) {
                $this->dbs->insertDate($entity->getId(), $type, $dbDate);
            } else {
                $this->dbs->updateDate($entity->getId(), $type, $dbDate);
            }
        }
    }

    protected function updatePublicComment(Entity $entity, string $publicComment = null): void
    {
        if (empty($publicComment)) {
            if (!empty($entity->getPublicComment())) {
                $this->dbs->updatePublicComment($entity->getId(), '');
            }
        } else {
            $this->dbs->updatePublicComment($entity->getId(), $publicComment);
        }
    }

    protected function updatePrivateComment(Entity $entity, string $privateComment = null): void
    {
        if (empty($privateComment)) {
            if (!empty($entity->getPrivateComment())) {
                $this->dbs->updatePrivateComment($entity->getId(), '');
            }
        } else {
            $this->dbs->updatePrivateComment($entity->getId(), $privateComment);
        }
    }
}
