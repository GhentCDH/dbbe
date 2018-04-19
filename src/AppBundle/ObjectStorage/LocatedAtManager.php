<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use AppBundle\Model\LocatedAt;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LocatedAtManager extends ObjectManager
{
    public function getLocatedAtsByIds(array $documentIds): array
    {
        list($cached, $documentIds) = $this->getCache($documentIds, 'located_at');
        if (empty($documentIds)) {
            return $cached;
        }

        $locatedAts = [];
        $rawLocatedAts = $this->dbs->getLocatedAtsByIds($documentIds);
        $locationIds = self::getUniqueIds($rawLocatedAts, 'location_id');
        $locations = $this->container->get('location_manager')->getLocationsByIds($locationIds);

        foreach ($rawLocatedAts as $rawLocatedAt) {
            $locatedAts[$rawLocatedAt['locatedat_id']] = (new LocatedAt())
                ->setId($rawLocatedAt['locatedat_id'])
                ->setLocation($locations[$rawLocatedAt['location_id']])
                ->setShelf($rawLocatedAt['shelf']);
        }

        $this->setCache($locatedAts, 'located_at');

        return $locatedAts;
    }

    public function updateLocatedAt(int $locatedAtId, stdClass $data): LocatedAt
    {
        $this->dbs->beginTransaction();
        try {
            $locatedAts = $this->getLocatedAtsByIds([$locatedAtId]);
            if (count($locatedAts) == 0) {
                throw new NotFoundHttpException('LocatedAt with id ' . $locatedAtId .' not found.');
            }
            $locatedAt = $locatedAts[$locatedAtId];

            // update locatedAt data
            $correct = false;
            if (property_exists($data, 'location')
                && property_exists($data->location, 'id')
                && is_numeric($data->location->id)
            ) {
                $correct = true;
                $this->dbs->updateLocation($locatedAtId, $data->location->id);
            }
            if (property_exists($data, 'shelf')
                && is_string($data->shelf)
            ) {
                $correct = true;
                $this->dbs->updateShelf($locatedAtId, $data->shelf);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new locationAt data
            $this->cache->invalidateTags(['located_at.' . $locatedAtId]);
            $this->cache->deleteItem('located_at.' . $locatedAtId);
            $newLocatedAt = $this->getLocatedAtsByIds([$locatedAtId])[$locatedAtId];
            $this->updateModified($locatedAt, $newLocatedAt);

            // update cache
            $this->setCache([$newLocatedAt->getId() => $newLocatedAt], 'located_at');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newLocatedAt;
    }
}
