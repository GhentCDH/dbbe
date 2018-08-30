<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class OriginService extends DatabaseService
{
    /**
     * Locations that can be used as origin in a manuscript
     * * institution needs to be a monastery
     * * need a region with a historical name (not name)
     * @return array
     */
    public function getOriginIdsForManuscripts(): array
    {
        return $this->conn->query(
            'SELECT
                location.idlocation as origin_id
            from data.location
            left join (
                select
                    institution.identity as institution_id,
                    institution.idregion as city_id,
                    institution.name as institution_name
                from data.institution
                inner join data.monastery on institution.identity = monastery.identity
             ) as moninst on location.idinstitution = moninst.institution_id
            inner join data.region on coalesce(moninst.city_id, location.idregion) = region.identity
            where region.historical_name is not null
            order by region.historical_name, moninst.institution_name'
        )->fetchAll();
    }

    /**
     * Locations that can be used as origination in a person
     * * need a region with a historical name (not name)
     * @return array
     */
    public function getOriginIdsForPersons(): array
    {
        return $this->conn->query(
            'SELECT
                location.idlocation as origin_id
            from data.location
            inner join data.region on location.idregion = region.identity
            where region.historical_name is not null
            order by region.historical_name'
        )->fetchAll();
    }
}
