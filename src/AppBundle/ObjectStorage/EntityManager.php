<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Model\Entity;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Identification;
use AppBundle\Model\Identifier;

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

    protected function setIdentifications(array &$entities): void
    {
        $rawIdentifiers = $this->dbs->getIdentifications(self::getIds($entities));
        foreach ($rawIdentifiers as $rawIdentifier) {
            $entities[$rawIdentifier['entity_id']]->addIdentification(
                new Identification(
                    new Identifier(
                        $rawIdentifier['identifier_id'],
                        $rawIdentifier['system_name'],
                        $rawIdentifier['name'],
                        $rawIdentifier['is_primary'],
                        $rawIdentifier['link']
                    ),
                    json_decode($rawIdentifier['identifiers']),
                    json_decode($rawIdentifier['authority_ids']),
                    json_decode($rawIdentifier['identifier_ids'])
                )
            );
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

    protected function updateIdentification(Entity $entity, Identifier $identifier, string $value): void
    {
        $this->dbs->beginTransaction();
        try {
            if (!empty($value) && !preg_match(
                '/' . $identifier->getRegex() . '/',
                $value
            )) {
                throw new BadRequestHttpException('Incorrect identification data.');
            }


            if ($identifier->getVolumes() > 1) {
                $newIdentifications = empty($value) ? [] : explode(', ', $value);
                $volumeArray = [];
                foreach ($newIdentifications as $identification) {
                    $volume = explode('.', $identification)[0];
                    if (in_array($volume, $volumeArray)) {
                        throw new BadRequestHttpException('Duplicate identification entry.');
                    } else {
                        $volumeArray[] = $volume;
                    }
                }

                $newArray = [];
                foreach ($newIdentifications as $newIdentification) {
                    list($volume, $id) = explode('.', $newIdentification);
                    $newArray[$volume] = $id;
                }

                $currentIdentifications = empty($entity->getIdentifications()[$identifier->getSystemName()]) ? [] : $entity->getIdentifications()[$identifier->getSystemName()]->getIdentifications();
                $currentArray = [];
                foreach ($currentIdentifications as $currentIdentification) {
                    list($volume, $id) = explode('.', $currentIdentification);
                    $currentArray[$volume] = $id;
                }

                $delArray = [];
                $upsertArray = [];
                for ($volume = 0; $volume < $identifier->getVolumes(); $volume++) {
                    $romanVolume = Identification::numberToRoman($volume +1);
                    // No old and no new value
                    if (!array_key_exists($romanVolume, $currentArray) && !array_key_exists($romanVolume, $newArray)) {
                        continue;
                    }
                    // Old value === new value
                    if (array_key_exists($romanVolume, $currentArray) && array_key_exists($romanVolume, $newArray)
                        && $currentArray[$romanVolume] === $newArray[$romanVolume]
                    ) {
                        continue;
                    }
                    // Old value, but no new value
                    if (array_key_exists($romanVolume, $currentArray) && !array_key_exists($romanVolume, $newArray)) {
                        $delArray[] = $volume;
                        continue;
                    }
                    // No old or different value
                    $upsertArray[$volume] = $newArray[$romanVolume];
                }

                foreach ($delArray as $volume) {
                    $this->dbs->delIdentification($entity->getId(), $identifier->getId(), $volume);
                }
                foreach ($upsertArray as $volume => $value) {
                    $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), $volume, $value);
                }
            } else {
                // Old value, but no new value
                if (!empty($entity->getIdentifications()[$identifier->getSystemName()]) && empty($value)) {
                    $this->dbs->delIdentification($entity->getId(), $identifier->getId(), 0);
                } elseif ((empty($entity->getIdentifications()[$identifier->getSystemName()]) && !empty($value)) // No old value
                    || (!empty($entity->getIdentifications()[$identifier->getSystemName()]) && !empty($value) && $entity->getIdentifications()[$identifier->getSystemName()] !== $value) // Different old value
                ) {
                    $this->dbs->upsertIdentification($entity->getId(), $identifier->getId(), 0, $value);
                }
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
    }
}
