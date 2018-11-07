<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use AppBundle\Model\LocatedAt;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LocatedAtManager extends ObjectManager
{
    public function get(array $documentIds): array
    {
        $locatedAts = [];
        $rawLocatedAts = $this->dbs->getLocatedAtsByIds($documentIds);
        $locationIds = self::getUniqueIds($rawLocatedAts, 'location_id');
        $locations = $this->container->get('location_manager')->get($locationIds);

        foreach ($rawLocatedAts as $rawLocatedAt) {
            $locatedAts[$rawLocatedAt['locatedat_id']] = (new LocatedAt())
                ->setId($rawLocatedAt['locatedat_id'])
                ->setLocation($locations[$rawLocatedAt['location_id']])
                ->setShelf($rawLocatedAt['shelf'])
                ->setExtra($rawLocatedAt['extra']);
        }

        return $locatedAts;
    }

    public function addLocatedAt(int $locatedAtId, stdClass $data): LocatedAt
    {
        $this->dbs->beginTransaction();
        try {
            // add locatedAt data
            if (property_exists($data, 'location')
                && property_exists($data->location, 'id')
                && is_numeric($data->location->id)
                && property_exists($data, 'shelf')
                && is_string($data->shelf)
                && (
                    !property_exists($data, 'extra')
                    || (empty($data->extra) || is_string($data->extra))
                )
            ) {
                $this->dbs->insert($locatedAtId, $data->location->id, $data->shelf, property_exists($data, 'extra') ? $data->extra : null);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new locationAt data
            $newLocatedAt = $this->get([$locatedAtId])[$locatedAtId];

            $this->updateModified(null, $newLocatedAt);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newLocatedAt;
    }

    public function updateLocatedAt(int $locatedAtId, stdClass $data): LocatedAt
    {
        $this->dbs->beginTransaction();
        try {
            $locatedAts = $this->get([$locatedAtId]);
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
            if (property_exists($data, 'extra')
                && empty($data->extra) || is_string($data->extra)
            ) {
                $correct = true;
                $this->dbs->updateExtra($locatedAtId, $data->extra);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new locationAt data
            $newLocatedAt = $this->get([$locatedAtId])[$locatedAtId];

            $this->updateModified($locatedAt, $newLocatedAt);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newLocatedAt;
    }
}
