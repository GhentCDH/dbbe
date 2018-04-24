<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Occurrence;

class OccurrenceManager extends ObjectManager
{
    public function getOccurrencesByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'occurrence');
        if (empty($ids)) {
            return $cached;
        }

        $occurrences = [];
        $rawOccurrences = $this->dbs->getOccurrencesByIds($ids);

        foreach ($rawOccurrences as $rawOccurrence) {
            $occurrences[$rawOccurrence['occurrence_id']] = (new Occurrence())
                ->setId($rawOccurrence['occurrence_id'])
                ->setFoliumStart($rawOccurrence['folium_start'])
                ->setFoliumStartRecto($rawOccurrence['folium_start_recto'])
                ->setFoliumEnd($rawOccurrence['folium_end'])
                ->setFoliumEndRecto($rawOccurrence['folium_end_recto'])
                ->setGeneralLocation($rawOccurrence['general_location'])
                ->setIncipit($rawOccurrence['incipit']);
        }

        $this->setCache($occurrences, 'occurrence');

        return $cached + $occurrences;
    }

    public function getOccurrencesDependenciesByManuscript(int $manuscriptId): array
    {
        $rawIds = $this->dbs->getDepIdsByManuscriptId($manuscriptId);
        return $this->getOccurrencesByIds(self::getUniqueIds($rawIds, 'occurrence_id'));
    }
}
