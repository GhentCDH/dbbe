<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Origin;

class OriginManager extends ObjectManager
{
    public function getAllOrigins(): array
    {
        $origins = [];
        $rawOrigins = $this->dbs->getOriginIds();
        $originIds = self::getUniqueIds($rawOrigins, 'origin_id');
        $locations = $this->container->get('location_manager')->getLocationsByIds($originIds);
        foreach ($locations as $location) {
            $origins[$location->getId()] = Origin::fromLocation($location);
        }

        usort($origins, ['AppBundle\Model\Location', 'sortByHistoricalName']);

        return $origins;
    }
}
